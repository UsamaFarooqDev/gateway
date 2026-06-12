# PowerCabs — Claude Code Project Memory

## Project Overview

**PowerCabs** is an Irish ride-hailing platform with Flutter mobile apps (Passenger + Driver) and a Supabase backend. Currently building the **Admin Panel** to manage rides, drivers, passengers, corporate accounts, financials, analytics, and platform settings.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Mobile Apps | Flutter (Dart) — Passenger & Driver apps |
| Backend | Supabase (PostgreSQL, Auth, Realtime, Storage, Edge Functions) |
| Admin Panel | PHP backend + Bootstrap 5 frontend (glassy/glassmorphism UI) |
| Real-time | WebRTC with TURN server |
| Email | Supabase Auth emails (custom PCTheme templates) |
| Maps | Google Maps API |
| Notifications | Firebase Push Notifications |

---

## Supabase

- **Project URL:** `https://ijrnahatonxpuzwjtykd.supabase.co`
- **Anon key:** stored in Flutter `lib/core/constants/supabase_constants.dart`
- Auth uses `verifyOtp({ token_hash, type })` — NOT the old `verify` method
- Password reset uses `{{ .TokenHash }}` as query param (hash-stripping fix)
- Deep link URI scheme: `io.supabase.powercabs://login-callback/`
- Package used for deep links: `app_links`

### Key Tables
- `public.drivers` — driver profiles, vehicle info, documents
- `public.passengers` — passenger profiles
- `public.rides` — ride records with status, route, fare
- Vehicle type stored as **jsonb array**: `[economy]` or `[economy, economy_xl]`
  - 4 seats → `[economy]`
  - 5+ seats → `[economy, economy_xl]`

---

## Flutter App Structure

### Apps
- `pw_app_passenger` — Passenger app
- `pw_app_driver` — Driver app

### Conventions
- State management: (confirm with codebase — likely Riverpod or Provider)
- Use `async`/`await` throughout — no raw `.then()` chains
- Always handle Supabase errors with try/catch and surface meaningful messages
- Use `const` constructors wherever possible
- Separate UI, logic, and data layers (feature-based folder structure preferred)

### Android Build
- Android SDK path: `C:\Android\sdk` (spaces in path break `flutter build appbundle`)
- Signing: `upload-key.jks` + `key.properties`
- Background location requires prominent disclosure dialog before requesting permission
- `USE_FULL_SCREEN_INTENT` must be justified or removed for Play Store

### iOS Build
- Distribution via Apple Developer account
- `Info.plist` must include `NSPhotoLibraryUsageDescription` and all used permission strings
- Deep link registered in `Info.plist` URL schemes

---

## WebRTC / TURN

- **Server:** `free.expressturn.com:3478`
- **Username:** `000000002091984498`
- **Credential:** `kwzWwHwPxvcD7QYFl0clT78xCqo=`

---

## Admin Panel — Module List

The admin panel covers **17 modules**:

1. Dashboard
2. Ride Management
3. Dispatcher Console
4. Driver Management
5. Passenger Management
6. Corporate Accounts
7. Fleet Management
8. Finance & Payments
9. Promotions & Pricing
10. Zones & Coverage
11. Notifications & Alerts
12. Analytics & Reports
13. Support & Disputes
14. Ratings & Reviews
15. Settings & Configuration
16. Admin User Management
17. Integrations

---

## Admin Panel — Tech Stack & Architecture

### Backend (PHP)
- **Language:** PHP 8.x
- **Pattern:** MVC — controllers in `app/controllers/`, models in `app/models/`, views in `app/views/`
- **Database:** MySQL via PDO (prepared statements only — no raw string queries)
- **Auth:** Session-based PHP auth with role-based access control (RBAC)
- **API:** RESTful JSON endpoints for AJAX calls from frontend (`api/` folder)
- **Config:** `config/database.php` for DB credentials — never hardcode credentials inline
- **Routing:** Simple front-controller pattern via `index.php` + `.htaccess` rewrite rules

### Frontend (Bootstrap 5 + Glassmorphism)
- **Framework:** Bootstrap 5.3
- **Theme:** Glassmorphism — frosted glass cards, blurred backgrounds, subtle transparency
- **Icons:** Bootstrap Icons or Remix Icons
- **Charts:** Chart.js for analytics/dashboards
- **Maps:** Google Maps JS API (live driver map, zone editor)
- **AJAX:** Vanilla JS `fetch()` for all dynamic data — no jQuery
- **Fonts:** Poppins (Google Fonts)

### UI Design Rules — STRICTLY FOLLOW
- Background: deep dark `#0D0D1A` or `#1A1A2E` with subtle radial gradient
- Glass cards: `background: rgba(255,255,255,0.05)`, `backdrop-filter: blur(12px)`, `border: 1px solid rgba(255,255,255,0.1)`, `border-radius: 16px`
- Primary accent: `#F37A20` (orange) for buttons, active states, highlights, chart colors
- Secondary accent: `rgba(243,122,32,0.15)` for soft orange glows and hover states
- Text: `#FFFFFF` primary, `rgba(255,255,255,0.6)` secondary/muted
- Buttons: orange gradient `linear-gradient(135deg, #F37A20, #e06010)` with `box-shadow: 0 4px 15px rgba(243,122,32,0.3)`
- Sidebar: glass panel, dark, with orange active indicator
- Tables: glass background, subtle row hover with orange tint
- Inputs: dark glass `background: rgba(255,255,255,0.07)`, orange focus border
- Badges/pills: semi-transparent colored backgrounds
- Minimal, clean — no clutter, generous whitespace, smooth transitions (`transition: all 0.3s ease`)

### Folder Structure
```
admin/
├── index.php                  # Front controller
├── .htaccess                  # URL rewriting
├── config/
│   └── database.php           # DB config
├── app/
│   ├── controllers/           # PHP controllers (one per module)
│   ├── models/                # PHP models (DB queries)
│   └── views/                 # PHP view templates
├── api/                       # JSON API endpoints (AJAX)
├── assets/
│   ├── css/
│   │   └── theme.css          # Custom glass theme styles
│   ├── js/
│   │   └── app.js             # Global JS utilities
│   └── img/
│       └── logo.svg           # PowerCabs logo
├── includes/
│   ├── header.php             # HTML head + nav
│   ├── sidebar.php            # Sidebar nav (all 17 modules)
│   └── footer.php             # Scripts + closing tags
└── vendor/                    # Composer dependencies
```

### PHP Code Style
- PHP 8 — use typed properties, match expressions, named arguments where appropriate
- Always use PDO with prepared statements — never interpolate user input into SQL
- Use `htmlspecialchars()` on all output to prevent XSS
- Controllers are thin — logic lives in models
- Return JSON from API endpoints: `header('Content-Type: application/json'); echo json_encode($data);`
- Error responses: `['success' => false, 'message' => '...']`
- Success responses: `['success' => true, 'data' => [...]]`

### Admin Roles
- `super_admin` — full access
- `dispatcher` — Dispatcher Console + Ride Management
- `finance` — Finance & Payments + Reports
- `support` — Support & Disputes + Ratings
- `fleet_manager` — Fleet + Driver Management

---

## Branding / Design

- **Primary color:** `#F37A20` (orange)
- **Dark background:** `#1A1A2E`
- **Font:** Poppins
- **Brand name:** PowerCabs
- **Domain:** powercabs.ie
- Email templates follow **PCTheme** — dark mode, orange accents, Poppins, SVG logo

---

## Code Style

- **Dart/Flutter:** follow official Dart style guide; use `lints` or `flutter_lints`
- **SQL / Supabase:** use Row Level Security (RLS) on all tables; never expose service role key client-side
- **Naming:** `snake_case` for DB columns/tables, `camelCase` for Dart variables, `PascalCase` for classes
- **Comments:** write comments for non-obvious logic; avoid redundant comments
- Prefer named parameters for functions with 3+ arguments

---

## Common Commands

```bash
# Flutter
flutter pub get
flutter build appbundle --release
flutter build ipa --release
flutter run --flavor production

# Supabase CLI
supabase start
supabase db push
supabase functions deploy <function-name>
supabase gen types typescript --linked > lib/database.types.ts
```

---

## Known Issues / Gotchas

- Android SDK must be at `C:\Android\sdk` — spaces in path break native builds
- `keytool` requires full JDK path on Windows PowerShell: `C:\Android\sdk\build-tools\<version>\...`
- Supabase free plan has egress limits — always paginate queries and select only needed columns
- iOS error 90683 = missing `NSPhotoLibraryUsageDescription` in `Info.plist`
- Google Play rejects `ACCESS_BACKGROUND_LOCATION` without a prominent in-app disclosure dialog shown *before* the system permission prompt

---

## Environment

- **Developer OS:** Windows
- **IDE:** VS Code with Claude Code extension
- **Version Control:** Git
- **Target platforms:** Android (Play Store) + iOS (App Store)
- **Region:** Ireland (Irish ride-hailing market)

---

## Out of Scope

- Do not add analytics/tracking SDKs without asking
- Do not change Supabase project URL or auth config without confirmation
- Do not modify signing keys or certificate files
- Do not switch admin panel to a JS framework (React/Vue/etc.) unless explicitly asked — keep it PHP + Bootstrap
