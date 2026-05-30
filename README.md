# MathLab – میزکار ریاضی

![Version](https://img.shields.io/badge/version-2.0.0--stable-blue)
![License](https://img.shields.io/badge/license-GPL--3.0-green)
![PHP](https://img.shields.io/badge/php-%3E%3D7.4-777BB4)
![Composer](https://img.shields.io/badge/composer-required-orange)
![Packagist Version](https://img.shields.io/packagist/v/sobhanmohammadi/cas?label=cas%20engine&color=blue)
![JavaScript](https://img.shields.io/badge/javascript-ES6-F7DF1E)

> **A Visual Mathematical Expression Builder & Computer Algebra System Workspace**

MathLab is an innovative web-based platform that bridges the gap between mathematical notation and computation. Designed for the **Kharazmi Festival**, it provides an intuitive drag-and-drop interface for constructing and solving mathematical expressions with full support for multiple languages (English & Persian).

---

## 🎯 Overview

MathLab revolutionizes how students, educators, and mathematicians interact with complex mathematical expressions. Instead of typing cryptic syntax, users can:
- **Visually construct** mathematical equations using draggable elements
- **Solve equations** with a powerful, modular computer algebra system
- **Validate expressions** in real-time
- **Copy and share** results instantly
- **Switch between languages** (English & Persian) seamlessly

---

## ✨ Key Features

### 🎨 Visual Expression Builder
- **Drag-and-drop interface** for intuitive expression construction
- **Rich element library** including:
  - Numbers and decimals
  - Basic operations (+, −, ×, ÷)
  - Advanced operations (fractions, radicals, powers)
  - Variables and symbolic manipulation
  - Mathematical symbols and operators
- **Real-time equation preview** with LaTeX rendering

### 🔬 Computer Algebra System (CAS Engine)
- The core logic is powered by our standalone [**sobhanmohammadi/cas**](https://packagist.org/packages/sobhanmohammadi/cas) library.
- **Equation solving** with support for:
  - Linear equations
  - Multi-variable systems
  - Symbolic manipulation
- **Error detection** and descriptive feedback
- **Variable substitution** and expression evaluation

### 🌍 Multilingual Support
- **Full Persian (RTL) interface** with proper directionality
- **English support** with complete localization
- **Easy language switching** via in-app button
- **Comprehensive i18n** for all UI strings

### 🔒 Security & Performance
- **Content Security Policy (CSP)** headers to prevent XSS attacks
- **Rate limiting** with IP-based tracking
- **Input sanitization** for safe expression handling
- **Optimized performance** with debouncing and throttling
- **No external dependencies** for frontend core functionality (optional CDN for enhancements)

### 📱 Responsive Design
- **Mobile-friendly** interface
- **Touch-optimized** controls and interactions
- **Adaptive layout** for all screen sizes

---

## 📋 System Requirements

### Minimum Requirements
- **PHP**: 7.4 or higher
- **Composer**: Required for backend dependencies
- **Web Server**: Apache with `mod_rewrite` or Nginx
- **Browser**: Modern browser with ES6 JavaScript support
  - Chrome/Edge 51+
  - Firefox 54+
  - Safari 10+

---

## 🚀 Installation & Setup

### 1. **Clone the Repository & Install Dependencies**
The backend calculation engine relies on our dedicated Composer package. You must install the dependencies before running the application.

```bash
# Clone the repository
git clone https://github.com/sobhanmohammadi-dev/MathLab.git
cd MathLab

# Navigate to the app directory and install dependencies
cd app
composer install
cd ..
```

Created with ❤️ and ☕ for **Kharazmi Festival**