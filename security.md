# 🔐 Security Policy

## 📌 Overview

Dokumen ini menjelaskan kebijakan keamanan untuk proyek **Hotel Booking Secure System**. Tujuan utama dari kebijakan ini adalah untuk memastikan bahwa setiap potensi kerentanan (vulnerability) dapat ditangani dengan baik dan tidak disalahgunakan.

---

## 🚨 Reporting Vulnerabilities

Jika Anda menemukan kerentanan keamanan dalam sistem ini, harap untuk:

1. Tidak menyebarkan atau mempublikasikan kerentanan tersebut secara publik
2. Melaporkan secara langsung kepada tim pengembang melalui:

   * GitHub Issues (private jika memungkinkan)
   * Atau melalui kontak tim (jika tersedia)

Kami akan meninjau laporan tersebut dan melakukan perbaikan secepat mungkin.

---

## 🛡️ Supported Security Practices

Proyek ini menerapkan beberapa praktik keamanan berikut:

* Penggunaan HTTPS untuk komunikasi data
* Hashing password menggunakan bcrypt atau Argon2
* Validasi input untuk mencegah SQL Injection dan XSS
* Role-Based Access Control (RBAC) untuk otorisasi
* Penggunaan environment variables untuk menyimpan data sensitif
* Tidak menyimpan credential sensitif di dalam repository

---

## ⚠️ Security Guidelines

Untuk menjaga keamanan proyek, mohon perhatikan hal berikut:

* Jangan menyimpan file `.env` ke dalam repository
* Jangan menyimpan API key, password, atau credential dalam kode sumber
* Gunakan data dummy untuk testing
* Pastikan semua dependency dalam kondisi aman dan up-to-date

---

## 📅 Security Updates

Perbaikan keamanan akan dilakukan secara berkala selama proses pengembangan proyek berlangsung.

---

## 📌 Disclaimer

Proyek ini dikembangkan untuk tujuan akademik dalam mata kuliah Secure Software Engineering. Meskipun demikian, praktik keamanan tetap diterapkan sesuai dengan standar industri.

---
