# Wheelsturn mobile app (Expo)

One app, three modes. The signed-in account decides the mode:

| Mode | Who | Highlights |
|---|---|---|
| Manager | admin, manager, advisor, accountant | Live dashboard with charts, check-in, job cards, diary and booking requests, customers, vehicles, invoices (payments with slip photos, pay links, WhatsApp), quotes, stock, tasks, AI assistant |
| Technician | mechanic | My jobs, workshop board, job clock, checklist, traffic-light inspection, parts by barcode, labour, photos and video, damage map, customer signature. Prices are hidden |
| Customer | customer portal users | Live vehicle progress, approve or decline extra work, photos from the workshop, pay invoices (PayFast, Ozow), accept quotes, book a service, service history |

The app talks to the same backend as the website at `https://app.wheelsturn.co.za/api/v1` (set in `app.json` under `extra.apiUrl`).

## Try it on your phone today (Expo Go)

1. Install **Expo Go** from the Play Store or App Store.
2. On this computer, in this folder: `npx expo start`
3. Scan the QR code with the phone (Android: inside Expo Go; iPhone: the Camera app).
4. Sign in with a workshop account. Customers sign in with the login from their portal invitation (Customers > Portal access on the web).

To test against the local backend instead of the live site, start the backend with `php spark serve --host 0.0.0.0`, then on the sign-in screen tap **Server settings** and enter `http://<this-computer's-LAN-IP>:8080`.

Push notifications do not work in Expo Go on Android; everything else does. Use an installed build (below) for push.

## Install builds (APK and store)

Builds run in Expo's cloud, so no Android Studio or Xcode is needed. You need a free Expo account.

```bash
npx eas-cli@latest login
npx eas-cli@latest init          # links the project and adds the EAS projectId (needed for push)
npx eas-cli@latest build -p android --profile preview      # installable APK to share with staff
npx eas-cli@latest build -p android --profile production   # Play Store bundle
npx eas-cli@latest build -p ios --profile production       # App Store (needs an Apple Developer account)
npx eas-cli@latest submit -p android                       # upload to Google Play
```

App identifiers: `za.co.wheelsturn.app` (Android package and iOS bundle id).

## Checks

```bash
npx tsc --noEmit   # typecheck
npx expo lint      # lint
npx expo-doctor    # dependency health
```

## Structure

- `src/app/` screens (Expo Router). `(staff)/` and `(customer)/` are the tab layouts per mode.
- `src/components/` UI kit, charts (SVG), workshop components (damage diagram, signature pad, scanner, media gallery).
- `src/lib/` API client (JWT in the device keychain), auth, push, formatting, types.
