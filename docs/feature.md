# Driver Feature Scope

Tasklist for development of driver-related features.

## 1. Driver Onboarding & Authentication
- [x] Implement API Authentication (Sanctum) for Driver mobile app.
- [x] Create Driver Registration/Profile completion API.
- [x] Create Driver login API.

## 2. Driver Availability & Location Tracking
- [ ] Implement toggle status API (online/offline).
- [ ] Create endpoint to push real-time GPS coordinates.
- [ ] Scheduled task to purge/archive old location history.

## 3. Delivery Management
- [ ] Implement API to fetch assigned deliveries (pending/active).
- [ ] Implement API to accept/reject delivery (if applicable).
- [ ] Implement status transitions for deliveries (`pending` -> `on_the_way` -> `completed`).
- [ ] Add support for delivery route optimization/summary.

## 4. Admin Integration (Filament)
- [ ] Add Driver Verification dashboard (Approve/Reject).
- [ ] Add Map/Location visualization for active drivers in Filament.
- [ ] Add report dashboard for driver earnings/efficiency.

## 5. Security & Validation
- [ ] Implement authorization policies for Driver APIs.
- [ ] Validate delivery constraints (max orders/weight) in API requests.
- [ ] Add API rate limiting for tracking updates.
