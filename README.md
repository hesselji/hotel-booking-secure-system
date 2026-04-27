# 🏨 Hotel Booking Secure System

## 👥 Kelompok

1. Hessel Josef Imanuel (2330205030062)
2. Apreldiovano Brian Walmaputra (2330305030089)
3. Rafael Sanjaya (2330305030075)

---
## 👥 Pembagian Tugas (Minggu 1)

Berikut pembagian tugas anggota tim pada Minggu 1 (Threat Modeling & Security Design):

* **Rafael Sanjaya**
  Bertanggung jawab pada penyusunan **SRS-Security**, termasuk:

  * Deskripsi sistem
  * Identifikasi aset kritis
  * Security requirements
  * Stakeholder & trust boundary

* **Apreldiovano Brian Walmaputra**
  Bertanggung jawab pada **Threat Modeling**, meliputi:

  * Analisis STRIDE (minimal 15 ancaman)
  * Penyusunan mitigasi ancaman
  * Pembuatan Attack Tree

* **Hessel Josef Imanuel**
  Bertanggung jawab pada **perancangan sistem dan visualisasi**, meliputi:

  * Data Flow Diagram (DFD) Level 0 & Level 1
  * Diagram arsitektur sistem
  * Struktur dokumentasi

Seluruh anggota tim juga berkolaborasi dalam:

* Integrasi laporan akhir Minggu 1
* Review dan perbaikan dokumen
* Penyusunan repository dan dokumentasi

---


## 📖 Deskripsi Sistem

Hotel Booking Secure System adalah aplikasi berbasis web yang dirancang untuk memungkinkan pengguna melakukan pencarian kamar, reservasi hotel, serta pembayaran secara online dengan pendekatan **Security by Design**.

Proyek ini dikembangkan dalam mata kuliah **Secure Software Engineering**, dengan fokus pada identifikasi ancaman, perancangan sistem yang aman, serta penerapan praktik keamanan sejak tahap awal pengembangan.

---

## 🎯 Tujuan Proyek

* Menerapkan konsep **Secure Software Development Life Cycle (S-SDLC)**
* Mengidentifikasi dan menganalisis ancaman menggunakan **STRIDE**
* Mendesain sistem yang aman berbasis **Security by Design**
* Mendokumentasikan kebutuhan keamanan dalam bentuk **SRS-Security**

---

## 🔐 Fokus Keamanan

* 🔑 Authentication & Session Management
* 🛡️ Authorization (RBAC & Least Privilege)
* 🔒 Data Protection (Encryption & Secure Storage)
* 📊 Threat Modeling (STRIDE & Attack Tree)
* 🌐 Secure Architecture & Trust Boundary

---

## 🧠 Metodologi yang Digunakan

* Secure Software Development Life Cycle (S-SDLC)
* Threat Modeling (STRIDE)
* Data Flow Diagram (DFD)
* Attack Tree Analysis
* Security Requirements Engineering

---

## 🛠️ Tech Stack

* **Frontend** : Web Application (HTML, CSS, JavaScript)
* **Backend**  : PHP (Native / Laravel)
* **Database** : MySQL / MariaDB
* **Security** : bcrypt, HTTPS, RBAC, Input Validation

---

## 📂 Struktur Project

```
hotel-booking-secure-system/
 ├── frontend/        # UI / Client-side
 ├── backend/         # Logic & server-side (PHP)
 ├── docs/            # Dokumentasi (SRS, DFD, STRIDE, dll)
 │    ├── 01_report/
 │    ├── 02_srs/
 │    ├── 03_threat_modeling/
 │    └── 04_architecture/
 ├── README.md
 ├── .gitignore
 └── SECURITY.md
```

---

## 📄 Dokumentasi

Seluruh dokumentasi proyek tersedia pada folder `docs/`:

* **SRS-Sec** (Security Requirements Specification)
* **DFD Level 0 & Level 1**
* **STRIDE Threat Modeling**
* **Attack Tree**
* **System Architecture**

---

## 📅 Progress Proyek

### Minggu 1 – Threat Modeling & Security Design

* [x] SRS-Sec
* [x] Identifikasi Aset Kritis
* [x] Threat Modeling (STRIDE)
* [x] DFD Level 0 & Level 1
* [x] Attack Tree
* [x] Arsitektur Sistem

---

## 🔄 Workflow Repository

Proyek ini menggunakan Git dengan sistem branching untuk mendukung kolaborasi tim:

* `main` → versi final/stable
* `develop` → penggabungan fitur
* `feature/*` → pengembangan fitur (authentication, booking, payment, dll)

Setiap perubahan dilakukan melalui:

1. Membuat branch dari `develop`
2. Commit dengan pesan yang jelas
3. Push dan membuat Pull Request
4. Review sebelum merge ke `develop`

---

## ⚠️ Security Notes

* Tidak ada data sensitif (API key, password, dll) yang disimpan di repository
* File `.env` tidak di-commit (diatur melalui `.gitignore`)
* Credential disimpan menggunakan environment variables
* Password di-hash menggunakan bcrypt

---

## 🚀 Status Proyek

📌 Currently in: **Security Design Phase (Week 1 Completed)**

---

## 📌 Catatan

Proyek ini dikembangkan untuk tujuan akademik dalam mata kuliah Secure Software Engineering dengan fokus pada penerapan praktik keamanan perangkat lunak secara menyeluruh.

---
