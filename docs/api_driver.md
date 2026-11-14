# API Driver Documentation

Semua endpoint API driver berada di bawah prefix `/api/driver`.

## Base URL
`{base_url}/api/driver`

## Header
Semua request yang membutuhkan autentikasi harus menyertakan header:
`Authorization: Bearer {token}`
`Accept: application/json`

---

## 1. Register
Endpoint untuk mendaftarkan driver baru.

- **URL**: `/api/driver/register`
- **Method**: `POST`
- **Request Body**:
  - `name` (string, required)
  - `email` (email, required, unique:users)
  - `password` (string, required, min:8)
  - `phone` (string, required)
  - `vehicle_type` (string, required)
  - `vehicle_plate` (string, required)
- **Response (201 Created)**:
```json
{
    "message": "Driver registered successfully",
    "token": "...",
    "user": { ... },
    "profile": { ... }
}
```

## 2. Login
Endpoint untuk login driver.

- **URL**: `/api/driver/login`
- **Method**: `POST`
- **Request Body**:
  - `email` (email, required)
  - `password` (string, required)
- **Response (200 OK)**:
```json
{
    "token": "...",
    "user": { ... },
    "profile": { ... }
}
```

## 3. Profil Driver (Me)
Mengambil data driver yang sedang login.

- **URL**: `/api/driver/me`
- **Method**: `GET`
- **Headers**: `Authorization: Bearer {token}`
- **Response (200 OK)**:
```json
{
    "user": { ... },
    "profile": { ... }
}
```

## 4. Logout
Mencabut token akses driver.

- **URL**: `/api/driver/logout`
- **Method**: `POST`
- **Headers**: `Authorization: Bearer {token}`
- **Response (200 OK)**:
```json
{
    "message": "Logged out"
}
```
