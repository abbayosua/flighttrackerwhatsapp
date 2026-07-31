# Plan: saas-flight-monitor

> Created: 2026-07-31 13:41:16
> **Status**: Draft

## Objective

Convert Python flight monitor (cekposisi) into a multi-user PHP SaaS: web login/dashboard for managing flights + WhatsApp recipients + per-user Wuzapi credentials (default shared abbayosua), MySQL persistence, and a cron runner that checks all active flights every 5 minutes and sends status + map via Wuzapi.

## Scope

**In Scope:**
-

**Out of Scope:**
-

## Context

Existing: /Users/user/www/cekposisi/cek_penerbangan.py (monitors SJV855 via FlightAware, sends text+map to 08117774884 & 081170004884 via wuzapi http://45.158.126.130:48499 token abbayosua). PHP 8.3.32 + MySQL 9.7.1 installed and running. Wuzapi API: POST /chat/send/text {Phone, Body}, POST /chat/send/image {Phone, Caption, Image(data uri)}, auth header Token: {token}. FlightAware map PNG: /ajax/flight/map/...?width=800&height=418&dpi=2. Must archive Python to old-implementation-python/.

## Acceptance Criteria

1) Register/login web UI. 2) Dashboard to add/edit/delete flights (URL, interval, send_map, mode always|on-change) and recipients (phone numbers). 3) Per-user wuzapi settings defaulting to shared 45.158.126.130/abbayosua. 4) cron/monitor.php checks all active flights, parses status, compares with DB last status, sends text+map to recipients. 5) Python moved to old-implementation-python/.

## Approach

-

## Tasks

| # | Task | Files | Status |
|---|------|-------|--------|
| 1 | - | - | pending |

## Risks & Mitigations

-

## Verification

- [ ] All tasks completed
- [ ] Tests pass
- [ ] Edge cases handled
