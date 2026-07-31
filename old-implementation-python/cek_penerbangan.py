#!/usr/bin/env python3
"""
Cek status penerbangan SJV855 dari FlightAware dan kirim ke WhatsApp via Wuzapi.
Periodik ngecek tiap N detik/menit. Serta kirim gambar peta posisi pesawat.
"""

import argparse
import base64
import json
import os
import re
import sys
import time
import urllib.request
import urllib.error
from datetime import datetime, timezone, timedelta
from html.parser import HTMLParser

WIB = timezone(timedelta(hours=7))

# ---------- KONFIGURASI ----------
FLIGHTAWARE_URL = "https://www.flightaware.com/live/flight/SJV855/history/20260728/0755Z/WIDD/WIII"
WUZAPI_URL = "http://45.158.126.130:48499"
WUZAPI_TOKEN = "CHANGE_ME"
WA_NUMBERS = ["08117774884", "081170004884"]
CHECK_INTERVAL_SECONDS = 300    # 5 menit
SEND_MAP = True                 # kirim gambar peta
ALWAYS_SEND = True              # kirim tiap interval meski status gak berubah
# ---------------------------------


class FlightStatusParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.in_title = False
        self.title_text = ""

    def handle_starttag(self, tag, attrs):
        if tag == "title":
            self.in_title = True

    def handle_endtag(self, tag):
        if tag == "title":
            self.in_title = False

    def handle_data(self, data):
        if self.in_title:
            self.title_text += data.strip()


def timestamp_wib() -> str:
    return datetime.now(WIB).strftime("%Y-%m-%d %H:%M:%S WIB")


def build_map_url(flight_url: str) -> str:
    """Derive map image URL from FlightAware flight URL."""
    m = re.match(r"https?://[^/]+/live/flight/(.+?)(?:/history)?(/.*?)/?$", flight_url)
    if m:
        base = m.group(1)  # e.g. "SJV855"
        rest = m.group(2) or ""  # e.g. "/20260728/0755Z/WIDD/WIII"
        return f"https://www.flightaware.com/ajax/flight/map/{base}{rest}/?width=800&height=418&dpi=2"
    return ""


def fetch_flightaware(url: str, timeout: int = 30) -> dict:
    """
    Ambil halaman FlightAware dan extract info status penerbangan.
    """
    req = urllib.request.Request(
        url,
        headers={
            "User-Agent": (
                "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/125.0.0.0 Safari/537.36"
            ),
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language": "en-US,en;q=0.5",
        },
    )

    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            html = resp.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as e:
        return {"error": f"HTTP {e.code}: {e.reason}", "status": "error", "fetched_at": timestamp_wib()}
    except urllib.error.URLError as e:
        return {"error": f"URLError: {e.reason}", "status": "error", "fetched_at": timestamp_wib()}
    except Exception as e:
        return {"error": str(e), "status": "error", "fetched_at": timestamp_wib()}

    parser = FlightStatusParser()
    parser.feed(html)
    title = parser.title_text.strip()

    status = extract_status(html, title)
    position = extract_position(html)

    result = {
        "status": status,
        "raw_title": title,
        "position": position,
        "fetched_at": timestamp_wib(),
    }

    # Extract origin/dest from URL for map
    route_match = re.search(r"/([A-Z]{4})/([A-Z]{4})(?:\?|$)", url)
    if route_match:
        result["origin"] = route_match.group(1)
        result["dest"] = route_match.group(2)

    return result


def extract_status(html: str, title: str) -> str:
    title_lower = title.lower()
    if "landed" in title_lower:
        return "Landed ✅"
    elif "en route" in title_lower or "in flight" in title_lower or "airborne" in title_lower:
        return "In Flight ✈️"
    elif "scheduled" in title_lower or "on time" in title_lower:
        return "Scheduled / On Time 🕐"
    elif "delayed" in title_lower or "delay" in title_lower:
        return "Delayed ⏰"
    elif "cancelled" in title_lower or "canceled" in title_lower:
        return "Cancelled ❌"
    elif "diverted" in title_lower:
        return "Diverted 🔄"
    elif "departed" in title_lower:
        return "Departed 🛫"
    elif "arrived" in title_lower:
        return "Arrived ✅"
    elif "unknown" in title_lower or "not found" in title_lower:
        return "Unknown / Not Found ❓"
    else:
        for keyword, label in [
            ("flight landed", "Landed ✅"), ("en route", "In Flight ✈️"),
            ("in flight", "In Flight ✈️"), ("airborne", "In Flight ✈️"),
            ("scheduled", "Scheduled 🕐"), ("departed", "Departed 🛫"),
            ("arrived", "Arrived ✅"), ("delayed", "Delayed ⏰"),
            ("cancelled", "Cancelled ❌"), ("diverted", "Diverted 🔄"),
        ]:
            if keyword in html.lower():
                return label
        return title if title else "Unknown ❓"


def extract_position(html: str) -> str:
    patterns = {
        "speed": r"(?i)(\d+)\s*knots?",
        "altitude": r"(?i)(\d[\d,]*)\s*(feet|ft)",
        "distance": r"(?i)(\d+)\s*miles?\s+(to|from)",
    }
    info = []
    for label, pat in patterns.items():
        match = re.search(pat, html)
        if match:
            info.append(match.group(0).strip())
    return ", ".join(info) if info else ""


def fetch_map_image(map_url: str, timeout: int = 15) -> bytes:
    """Download static map image from FlightAware."""
    req = urllib.request.Request(
        map_url,
        headers={
            "User-Agent": (
                "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
                "AppleWebKit/537.36 Chrome/125.0.0.0 Safari/537.36"
            ),
        },
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return resp.read()


def send_whatsapp_text(phone: str, message: str) -> dict:
    """Kirim text message WA via Wuzapi."""
    phone = format_phone(phone)
    payload = json.dumps({"Phone": phone, "Body": message}).encode("utf-8")
    return _wuzapi_request("/chat/send/text", payload)


def send_whatsapp_image(phone: str, caption: str, image_data: bytes, mime: str = "image/png") -> dict:
    """Kirim image WA via Wuzapi."""
    phone = format_phone(phone)
    b64 = base64.b64encode(image_data).decode()
    data_uri = f"data:{mime};base64,{b64}"
    payload = json.dumps({"Phone": phone, "Caption": caption, "Image": data_uri}).encode("utf-8")
    return _wuzapi_request("/chat/send/image", payload)


def format_phone(phone: str) -> str:
    phone = "".join(c for c in phone if c.isdigit())
    if phone.startswith("0"):
        phone = "62" + phone[1:]
    return phone


def _wuzapi_request(endpoint: str, payload: bytes, timeout: int = 30) -> dict:
    url = f"{WUZAPI_URL}{endpoint}"
    req = urllib.request.Request(
        url,
        data=payload,
        headers={"Content-Type": "application/json", "Token": WUZAPI_TOKEN},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            body = resp.read().decode("utf-8")
            result = json.loads(body)
            if result.get("success"):
                return {"ok": True, "response": result}
            return {"ok": False, "error": result.get("error", str(result))}
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        return {"ok": False, "error": f"HTTP {e.code}: {body}"}
    except urllib.error.URLError as e:
        return {"ok": False, "error": f"URLError: {e.reason}"}
    except Exception as e:
        return {"ok": False, "error": str(e)}


def format_status_text(flight_data: dict) -> str:
    status = flight_data.get("status", "Unknown")
    title = flight_data.get("raw_title", "")
    pos = flight_data.get("position", "")
    fetched = flight_data.get("fetched_at", "")

    lines = [f"✈️ *SJV855*", f"Status: {status}"]
    if title:
        lines.append(f"Info: {title[:120]}")
    if pos:
        lines.append(f"Detail: {pos}")
    lines.append(f"Check: {fetched}")
    return "\n".join(lines)


def check_and_notify(previous_status: str = "", send_map: bool = True, always: bool = True, phones: list | None = None) -> str:
    """
    Satu siklus: fetch FlightAware → kirim WA (text + map).
    always=True  → kirim tiap siklus.
    always=False → kirim cuma kalo status berubah.
    Returns status string, atau empty string kalo gagal fetch.
    """
    print(f"\n[{timestamp_wib()}] Fetching flight status...")
    data = fetch_flightaware(FLIGHTAWARE_URL)

    if "error" in data:
        msg = f"❌ Error FlightAware: {data['error']}"
        print(msg)
        for p in (phones or WA_NUMBERS):
            send_whatsapp_text(p, msg)
        return ""

    current_status = data.get("status", "")
    print(f"Status: {current_status}")
    if data.get("raw_title"):
        print(f"Title: {data['raw_title']}")
    if data.get("position"):
        print(f"Position: {data['position']}")

    # Kirim kalo: (1) status berubah, ATAU (2) mode always=True
    changed = current_status and current_status != previous_status
    should_send = changed or always

    if not should_send or not current_status:
        print(f"No change (still: {current_status}), skipping WA.")
        return current_status

    if changed:
        print(f"Status changed! Sending WA text...")
    else:
        print(f"Periodic update, sending WA text...")

    text_msg = format_status_text(data)
    for p in (phones or WA_NUMBERS):
        result_txt = send_whatsapp_text(p, text_msg)
        if result_txt.get("ok"):
            print(f"✅ WA text sent to {p} (ID: {result_txt['response']['data']['Id']})")
        else:
            print(f"❌ WA text to {p} failed: {result_txt.get('error')}")

    # Kirim map: kalo status berubah ATAU selalu (kalo always + changed aja biar irit)
    if send_map and (changed or always):
        map_url = build_map_url(FLIGHTAWARE_URL)
        if map_url:
            print(f"Downloading map image...")
            try:
                map_img = fetch_map_image(map_url)
                print(f"Map downloaded: {len(map_img)} bytes, sending WA image...")
                caption = f"🗺 SJV855 - {current_status} - {data.get('fetched_at', '')}"
                for p in (phones or WA_NUMBERS):
                    result_img = send_whatsapp_image(p, caption, map_img)
                    if result_img.get("ok"):
                        print(f"✅ WA map sent to {p} (ID: {result_img['response']['data']['Id']})")
                    else:
                        print(f"❌ WA map to {p} failed: {result_img.get('error')}")
            except Exception as e:
                print(f"❌ Map fetch/send failed: {e}")
        else:
            print(f"⚠️  Could not build map URL")

    return current_status


def main():
    global SEND_MAP

    parser = argparse.ArgumentParser(
        description="Cek status penerbangan SJV855 dan kirim ke WhatsApp (text + map)"
    )
    parser.add_argument("--interval", "-i", type=int, default=CHECK_INTERVAL_SECONDS,
                        help=f"Interval pengecekan dalam detik (default: {CHECK_INTERVAL_SECONDS})")
    parser.add_argument("--once", "-o", action="store_true",
                        help="Cek sekali aja, ga perlu looping")
    parser.add_argument("--phone", "-p", action="append", default=[],
                        help="Nomor WhatsApp tujuan (bisa dipake beberapa kali)")
    parser.add_argument("--no-map", action="store_true",
                        help="Jangan kirim gambar peta")
    parser.add_argument("--on-change", action="store_true",
                        help="Kirim cuma kalo status berubah (gak kirim tiap interval)")
    args = parser.parse_args()

    phones = args.phone if args.phone else WA_NUMBERS
    SEND_MAP = not args.no_map
    always = not args.on_change

    print("🚀 FlightAware → WhatsApp Monitor (with Map!)")
    print(f"   URL: {FLIGHTAWARE_URL}")
    print(f"   WA:  {phones}")
    print(f"   API: {WUZAPI_URL}")
    print(f"   Map: {'YES' if SEND_MAP else 'NO'}")
    print(f"   Mode: {'Periodic' if always else 'On Change Only'}")
    print(f"   Interval: {args.interval}s\n")

    # Notifikasi startup
    for p in phones:
        send_whatsapp_text(p, "🚀 Flight monitor SJV855 started!")
    print("✅ Startup notification sent!")

    previous_status = ""
    first_run = True

    try:
        while True:
            current_status = check_and_notify(
                previous_status if not first_run else "",
                send_map=SEND_MAP,
                always=always,
                phones=phones,
            )
            if current_status:
                previous_status = current_status
            first_run = False

            if args.once:
                print("\n✅ One-shot done.")
                break

            print(f"⏳ Next check in {args.interval}s...")
            time.sleep(args.interval)
    except KeyboardInterrupt:
        print("\n\n🛑 Monitor dihentikan user.")
        for p in phones:
            send_whatsapp_text(p, "🛑 Flight monitor SJV855 stopped.")
        sys.exit(0)


if __name__ == "__main__":
    main()
