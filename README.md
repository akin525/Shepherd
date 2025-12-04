# Shepherd HRMS API

A comprehensive Laravel-based REST API for the Shepherd Human Resource Management System mobile application.

## Features

- **Authentication & Authorization**: JWT-based authentication with role-based access control
- **Employee Management**: Complete CRUD operations for employee profiles
- **Attendance Management**: Check-in/check-out functionality with location tracking
- **Leave Management**: Leave requests, approvals, and balance tracking
- **Payroll System**: Payslip generation and salary management
- **Performance Management**: Goal tracking and performance appraisals
- **Announcements**: Company-wide announcements with read tracking
- **Asset Management**: Asset assignment and tracking
- **Profile Management**: Employee profile updates with avatar uploads

## API Documentation

### Base URL
```
http://localhost:8000/api
```

### Authentication

#### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "employee@example.com",
  "password": "password123",
  "device_name": "Mobile App"
}
```

#### Register
```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+1234567890",
  "device_name": "Mobile App"
}
```

#### Logout
```http
POST /api/auth/logout
Authorization: Bearer {token}
```

#### Get User Profile
```http
GET /api/auth/me
Authorization: Bearer {token}
```

### Employee Management

#### Get Employees
```http
GET /api/employees?search=John&department_id=1&per_page=15
Authorization: Bearer {token}
```

#### Get Employee Details
```http
GET /api/employees/{id}
Authorization: Bearer {token}
```

#### Create Employee (Admin Only)
```http
POST /api/employees
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "name": "New Employee",
  "email": "new@example.com",
  "employee_id": "EMP001",
  "department_id": 1,
  "designation_id": 1,
  "gender": "male",
  "dob": "1990-01-01",
  "date_of_joining": "2024-01-01",
  "salary": 50000,
  "salary_type": "monthly"
}
```

### Attendance Management

#### Check In
```http
POST /api/attendance/check-in
Authorization: Bearer {token}
Content-Type: application/json

{
  "latitude": 40.7128,
  "longitude": -74.0060,
  "location": "Office Building",
  "check_in_note": "Regular check-in"
}
```

#### Check Out
```http
POST /api/attendance/check-out
Authorization: Bearer {token}
Content-Type: application/json

{
  "latitude": 40.7128,
  "longitude": -74.0060,
  "location": "Office Building",
  "check_out_note": "Finished work for today"
}
```

#### Get My Attendance
```http
GET /api/attendance/my-attendance?month=1&year=2024&per_page=30
Authorization: Bearer {token}
```

#### Get Attendance Summary
```http
GET /api/attendance/summary?month=1&year=2024
Authorization: Bearer {token}
```

### Leave Management

#### Get Leave Types
```http
GET /api/leave-types
Authorization: Bearer {token}
```

#### Get Leave Balance
```http
GET /api/leave-balance
Authorization: Bearer {token}
```

#### Apply for Leave
```http
POST /api/leaves
Authorization: Bearer {token}
Content-Type: application/json

{
  "leave_type_id": 1,
  "start_date": "2024-02-01",
  "end_date": "2024-02-03",
  "reason": "Family vacation"
}
```

#### Get My Leaves
```http
GET /api/leaves?my_leaves=true&status=Pending&per_page=20
Authorization: Bearer {token}
```

### Payroll Management

#### Get My Payslips
```http
GET /api/payroll/payslips?year=2024&per_page=12
Authorization: Bearer {token}
```

#### Get Salary Breakdown
```http
GET /api/payroll/salary-breakdown
Authorization: Bearer {token}
```

#### Get Payslip Details
```http
GET /api/payroll/payslips/{id}
Authorization: Bearer {token}
```

### Performance Management

#### Get My Goals
```http
GET /api/performance/goals?status=In Progress&per_page=15
Authorization: Bearer {token}
```

#### Create Goal
```http
POST /api/performance/goals
Authorization: Bearer {token}
Content-Type: application/json

{
  "goal_type_id": 1,
  "subject": "Complete PHP Certification",
  "description": "Complete advanced PHP certification course",
  "start_date": "2024-01-01",
  "end_date": "2024-06-30",
  "target_value": 100,
  "unit": "percent",
  "priority": "High"
}
```

#### Get Performance Reviews
```http
GET /api/performance/reviews?year=2024&per_page=10
Authorization: Bearer {token}
```

### Announcements

#### Get Announcements
```http
GET /api/announcements?category=General&per_page=15
Authorization: Bearer {token}
```

#### Mark Announcement as Read
```http
POST /api/announcements/{id}/mark-read
Authorization: Bearer {token}
```

### Asset Management

#### Get My Assets
```http
GET /api/assets/my-assets?status=Assigned&per_page=15
Authorization: Bearer {token}
```

#### Return Asset
```http
POST /api/assets/{id}/return
Authorization: Bearer {token}
Content-Type: application/json

{
  "return_date": "2024-12-31",
  "return_condition": "Good",
  "return_notes": "Asset in good working condition"
}
```

### Profile Management

#### Get Profile
```http
GET /api/profile
Authorization: Bearer {token}
```

#### Update Profile
```http
PUT /api/profile
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "John Doe",
  "phone": "+1234567890",
  "address": "123 Main St, City, State",
  "emergency_contact": "+0987654321",
  "emergency_contact_name": "Jane Doe",
  "emergency_contact_relation": "Spouse",
  "skills": "PHP, Laravel, JavaScript, React",
  "qualifications": "B.Sc. Computer Science"
}
```

#### Update Avatar
```http
POST /api/profile/avatar
Authorization: Bearer {token}
Content-Type: multipart/form-data

avatar: [file]
```

#### Change Password
```http
POST /api/auth/change-password
Authorization: Bearer {token}
Content-Type: application/json

{
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```

## Response Format

All API responses follow a consistent format:

### Success Response
```json
{
  "status": true,
  "message": "Operation successful",
  "data": {
    // Response data here
  }
}
```

### Error Response
```json
{
  "status": false,
  "message": "Error message",
  "errors": {
    // Validation errors (if any)
  }
}
```

### Paginated Response
```json
{
  "status": true,
  "message": "Data retrieved successfully",
  "data": {
    "items": [
      // Array of items
    ],
    "pagination": {
      "total": 100,
      "count": 15,
      "per_page": 15,
      "current_page": 1,
      "total_pages": 7,
      "has_more_pages": true,
      "next_page_url": "http://localhost:8000/api/employees?page=2",
      "prev_page_url": null
    }
  }
}
```

## Authentication

All protected endpoints require a Bearer token in the Authorization header:

```
Authorization: Bearer {your_token_here}
```

## Error Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

## Rate Limiting

API endpoints are rate-limited to prevent abuse. Default limits:
- 60 requests per minute per IP address
- 1000 requests hour per authenticated user

## File Uploads

- Maximum file size: 5MB
- Supported formats: PDF, DOC, DOCX, JPG, JPEG, PNG
- Avatar images: Maximum 2MB, JPG/PNG format

## Setup Instructions

1. Clone the repository
2. Install dependencies: `composer install`
3. Configure environment variables
4. Run migrations: `php artisan migrate`
5. Generate application key: `php artisan key:generate`
6. Start development server: `php artisan serve`

## Environment Variables

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shepherd_hrms
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:8000,localhost:3000
```

## Security Features

- JWT-based authentication
- Role-based access control
- Request throttling
- Input validation and sanitization
- SQL injection protection
- XSS protection
- CORS configuration

## Support

For API support and documentation updates, please contact the development team.