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
  <img width="1700" height="792" alt="DFD Secure Software Engineering-DFD LEVEL 0_SISTEM BOOKING HOTEL drawio" src="https://github.com/user-attachments/assets/f926fb64-0287-438e-98b3-57626a0ffb81" />
  <img width="1700" height="835" alt="DFD Secure Software Engineering-DFD LEVEL 1_SISTEM BOOKING HOTEL drawio" src="https://github.com/user-attachments/assets/f88eb17a-9bec-40f4-ab31-afeb663dcb54" />

* **STRIDE Threat Modeling**
  <img width="1294" height="500" alt="image" src="https://github.com/user-attachments/assets/e7a7d0b2-97c2-4548-b082-fe61d25a562d" />
  <img width="1292" height="495" alt="image" src="https://github.com/user-attachments/assets/c03c2b4b-a07a-47eb-b438-0fb649a6b121" />
  <img width="1288" height="556" alt="image" src="https://github.com/user-attachments/assets/9f2bb62e-48b4-49b3-bd6f-c4a4be0664f0" />
  <img width="1296" height="580" alt="image" src="https://github.com/user-attachments/assets/d59f5d40-a70e-4233-8c79-a60260592220" />
  <img width="1294" height="337" alt="image" src="https://github.com/user-attachments/assets/cd5027d2-8af1-4595-9a42-9705261ae0e1" />
 
* **Attack Tree**
  <img width="2720" height="1239" alt="Attack Tree 1 drawio" src="https://github.com/user-attachments/assets/4c7d14ff-15ea-4150-8398-e210fa738cb5" />
  <img width="2653" height="817" alt="Attack Tree 2 drawio" src="https://github.com/user-attachments/assets/88275c0d-76f3-4566-96f2-1215f03de924" />
  
* **System Architecture**
  <img width="1322" height="733" alt="image" src="https://github.com/user-attachments/assets/ac59baab-7c6d-4a00-b354-0422a16de6c2" />


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

Proyek ini menggunakan Git dengan sistem branching untuk mendukung kolaborasi tim dan menjaga kualitas pengembangan.

### 🌿 Struktur Branch

* `main` → versi final / stabil (siap dikumpulkan atau demo)
* `develop` → tempat penggabungan semua fitur
* `feature/*` → pengembangan fitur utama (authentication, booking, payment, dll)
* `feature/integration` → tahap integrasi dan pengujian seluruh fitur sebelum finalisasi

---

### 🔁 Alur Pengembangan

1. Setiap anggota membuat branch dari `develop`
2. Pengembangan dilakukan pada branch `feature/*`
3. Perubahan di-commit dengan pesan yang jelas dan deskriptif
4. Branch di-push ke repository dan dibuat Pull Request ke `develop`
5. Setelah direview, fitur digabung ke `develop`
6. Semua fitur yang sudah lengkap diuji bersama di `feature/integration`
7. Setelah sistem stabil, hasil akhir di-merge ke `main`

---

### 📌 Tujuan Workflow

* Memisahkan pengembangan fitur agar tidak saling mengganggu
* Memastikan setiap perubahan melalui proses review
* Mengurangi konflik dan bug saat integrasi
* Menjaga stabilitas sistem sebelum rilis final

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
