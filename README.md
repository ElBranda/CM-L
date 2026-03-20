# CM - L
A modern full-stack management system for sports facilities, built with a focus on real-time scheduling and seamless user experience.

## Project Overview
This project was developed as a comprehensive solution for managing padel court bookings. It features a decoupled architecture with a high-performance frontend and a robust PHP backend.

## Tech Stack
### Frontend
* React 19: Utilizing the latest features for optimal performance and state management.
* Vite 7: High-speed development and bundling.
* React Router 7: Advanced client-side routing and data loading.

### Backend & Database
* PHP: Handling the business logic and API endpoints.
* MySQL: Relational database for persistent storage of bookings and user data.
* XAMPP/Apache: Local server environment for development.

## Getting Started
### Prerequisites
* Node.js (latest stable version).
* PHP 8.x and Composer.
* XAMPP or a similar local server environment.

## Installation
1. Clone the repository:
```Bash
git clone https://github.com/ElBranda/CM-L.git
cd CM-L
```

2. Frontend Setup:
```Bash
cd frontend
npm install
npm run dev
```

3. Backend Setup:
```Bash
cd ../backend
composer install
```
> Note: Ensure the backend folder is accessible by your local server (e.g., via a symlink in XAMPP's htdocs).

4. Database Configuration:
* Import the provided .sql file located in the /database folder into your MySQL server.
* Configure your database credentials in the backend's configuration file.

## Key Features
* Dynamic Booking System: Interactive calendar for managing court availability.
* User Management: Secure handling of client records and facility administrators.
* Responsive Design: Fully optimized for both desktop and mobile devices.