# PROJECT: Multi-Event Volunteer & Event Workforce Management Platform

Bangun sebuah **platform web multi-event** untuk mengelola recruitment, seleksi, penempatan, scheduling, attendance, komunikasi, dan evaluasi volunteer/crew untuk berbagai macam event seperti:

- Music festival
- Concert
- Campus event
- Community event
- Sport event
- Creative festival
- Conference
- Charity event
- Exhibition
- Public event

Platform harus dirancang sebagai **production-ready multi-tenant application** yang aman, scalable, maintainable, responsive, dan mudah dikembangkan.

Jangan membuat aplikasi sebagai website single-event.

Satu aplikasi harus mampu menangani:

```text
Platform
│
├── Organizer A
│   ├── Event A1
│   ├── Event A2
│   └── Event A3
│
├── Organizer B
│   ├── Event B1
│   └── Event B2
│
└── Organizer C
    └── Event C1
```

Semua data harus memiliki isolation berdasarkan ownership dan event scope.

---

# 1. PRODUCT VISION

Platform harus menjadi **centralized event workforce platform**.

Tujuan utama:

1. Organizer dapat membuat dan mengelola banyak event.
2. Satu event dapat memiliki banyak division, role, shift, dan volunteer.
3. Volunteer dapat mengikuti banyak event menggunakan satu akun.
4. Organizer dapat mengelola seluruh recruitment dan operasional volunteer.
5. Platform dapat digunakan kembali untuk event berikutnya tanpa membuat aplikasi baru.
6. Data antar organizer harus terisolasi.
7. Security harus diterapkan sejak level database hingga frontend.
8. Semua business-critical operation harus memiliki transaction dan validation.
9. Sistem harus mampu menangani concurrent registration.
10. Sistem harus siap dikembangkan menjadi SaaS / technology platform.

---

# 2. USER TYPES

Gunakan **RBAC + Permission + Resource Ownership + Event Scope**.

## Super Admin

Akses global platform.

Dapat:

- Mengelola seluruh user
- Mengelola organizer
- Mengelola seluruh event
- Mengelola permission
- Mengelola system settings
- Suspend account
- Activate account
- Melihat audit log
- Melihat security log
- Melihat platform analytics
- Melakukan moderation
- Melakukan maintenance operation

Super Admin adalah satu-satunya role dengan akses lintas seluruh tenant.

---

# 3. ORGANIZER / TENANT

Organizer merupakan tenant utama.

Contoh:

```text
Organizer A
Organizer B
Organizer C
```

Setiap organizer memiliki:

- Profile
- Logo
- Description
- Contact
- Email
- Phone
- Social media
- Website
- Branding
- Members
- Events
- Reports

Organizer tidak boleh mengakses tenant lain.

---

# 4. ORGANIZER MEMBER

Satu organizer dapat memiliki banyak staff.

Contoh:

```text
Organizer A
│
├── Owner
├── Event Manager
├── Volunteer Coordinator
├── HR
├── Supervisor
└── Staff
```

Permission harus configurable.

Contoh:

```text
event.create
event.update
event.delete
registration.read
registration.update
volunteer.assign
attendance.read
announcement.create
report.export
```

Jangan hardcode seluruh permission di frontend.

Authorization harus selalu diverifikasi di backend.

---

# 5. VOLUNTEER

Satu user volunteer dapat mengikuti banyak event.

Contoh:

```text
Volunteer A
├── Event A → Artist Liaison
├── Event B → Registration
├── Event C → Stage Crew
└── Event D → Runner
```

Volunteer memiliki:

- Profile
- Skills
- Experience
- Education
- Availability
- Portfolio
- Social links
- Emergency contact sesuai kebutuhan event
- Event history
- Attendance history
- Certificate history
- Rating / evaluation

Volunteer hanya boleh mengubah data miliknya sendiri.

---

# 6. EVENT

Setiap organizer dapat memiliki unlimited event sesuai package/subscription.

Event fields minimal:

- ID
- Organizer ID
- Name
- Slug
- Description
- Banner
- Thumbnail
- Category
- Venue
- Address
- Latitude / longitude jika diperlukan
- Start date
- End date
- Registration start
- Registration end
- Timezone
- Status
- Capacity
- Contact information
- Terms
- Privacy notice
- Publish status

Status:

```text
Draft
Published
Registration Open
Registration Closed
Ongoing
Completed
Cancelled
Archived
```

Gunakan valid state transition.

Contoh:

```text
Draft
↓
Published
↓
Registration Open
↓
Registration Closed
↓
Ongoing
↓
Completed
```

Jangan memungkinkan status invalid hanya melalui manipulasi request.

---

# 7. EVENT BRANDING

Setiap event dapat memiliki branding sendiri:

- Logo
- Banner
- Primary color
- Secondary color
- Typography preference
- Event description
- Sponsor section
- Social media links

Event branding tidak boleh mempengaruhi event lain.

---

# 8. EVENT DIVISION

Setiap event dapat mempunyai banyak division.

Contoh event musik:

```text
Music Festival
│
├── Registration
├── Stage
├── Artist Liaison
├── Security
├── Crowd Control
├── Hospitality
├── Documentation
├── Social Media
├── Merchandise
├── Runner
├── Medical
└── Technical
```

Division memiliki:

- Name
- Description
- Supervisor
- Status
- Event ID

---

# 9. VOLUNTEER ROLE

Setiap division dapat mempunyai banyak role.

Contoh:

```text
Artist Liaison
├── Artist Liaison A
├── Artist Liaison B
└── Artist Liaison C
```

Role memiliki:

- Name
- Description
- Division
- Capacity
- Requirements
- Skills
- Age requirement jika diperlukan
- Schedule
- Location
- Active status

Role quota harus dikontrol backend dan database.

---

# 10. CUSTOM REGISTRATION FORM

Organizer dapat membuat form pendaftaran custom untuk event.

Field types:

- Text
- Textarea
- Number
- Email
- Phone
- Date
- Time
- Select
- Multi Select
- Radio
- Checkbox
- URL
- File upload

Setiap field:

- Label
- Description
- Type
- Required
- Validation rule
- Placeholder
- Sort order
- Active status

Jangan menyimpan arbitrary HTML tanpa sanitization.

---

# 11. VOLUNTEER REGISTRATION FLOW

Flow:

```text
Browse Event
↓
Event Detail
↓
Select Role
↓
Registration Form
↓
Validation
↓
Submit
↓
Pending
↓
Review
↓
Accepted / Rejected / Waitlisted
↓
Assignment
↓
Schedule
↓
Attendance
↓
Evaluation
↓
Certificate
```

Status:

```text
Pending
Under Review
Accepted
Rejected
Waitlisted
Cancelled
Withdrawn
```

Status transition harus dikontrol oleh backend.

Volunteer tidak boleh mengubah status registration menjadi Accepted melalui API request.

---

# 12. DUPLICATE REGISTRATION PROTECTION

Sistem harus mencegah:

- User mendaftar event yang sama dua kali
- Duplicate accepted assignment
- Duplicate active registration
- Duplicate attendance
- Duplicate certificate

Gunakan kombinasi:

- Backend validation
- Unique database constraints
- Transaction
- Idempotency

Jangan mengandalkan JavaScript frontend.

---

# 13. QUOTA MANAGEMENT

Setiap event dan role dapat memiliki quota.

Contoh:

```text
Artist Liaison
Quota: 20

Accepted: 20

Available: 0
```

Ketika quota penuh:

- Registration tetap dapat ditutup
- Candidate dapat masuk waitlist
- Tidak boleh menerima volunteer ke role tersebut secara ilegal

Gunakan database transaction + row locking / atomic update sesuai database.

Harus aman terhadap:

```text
User A → register
User B → register
User C → register

secara bersamaan
```

Tidak boleh terjadi:

```text
Quota = 20
Accepted = 21
```

---

# 14. CONCURRENCY / RACE CONDITION

Secara khusus testing:

- Concurrent registration
- Concurrent acceptance
- Concurrent assignment
- Concurrent cancellation
- Concurrent check-in

Gunakan transaction pada operasi kritis.

Jangan membuat logic:

```text
if quota_available:
    create_registration()
```

tanpa transaction/locking.

---

# 15. VOLUNTEER SELECTION

Organizer dapat:

- Search
- Filter
- Sort
- Review candidate
- View profile
- View experience
- Accept
- Reject
- Waitlist
- Assign role
- Reassign role
- Bulk action

Bulk action harus tetap melewati authorization dan validation.

---

# 16. ROLE ASSIGNMENT

Volunteer dapat diberikan assignment:

```text
Event
↓
Division
↓
Role
↓
Shift
↓
Location
↓
Supervisor
```

Assignment harus memiliki history.

Contoh:

```text
Assigned
→ Reassigned
→ Confirmed
→ Completed
```

Jangan menghapus history assignment secara permanen tanpa alasan audit.

---

# 17. SHIFT MANAGEMENT

Organizer dapat membuat:

- Shift
- Date
- Start time
- End time
- Location
- Capacity
- Supervisor

Contoh:

```text
18 September

16:00 - 18:00
Registration Gate A

18:00 - 20:00
Crowd Control

20:00 - 22:00
Stage Support
```

Volunteer melihat hanya shift yang relevan dengan assignment-nya.

---

# 18. SCHEDULE

Buat personal schedule untuk volunteer.

Contoh:

```text
MY EVENT SCHEDULE

17 Sept
16:00 - 18:00
Registration Gate A

17 Sept
20:00 - 23:00
Artist Liaison

18 Sept
15:00 - 18:00
Runner
```

Organizer memiliki centralized schedule management.

---

# 19. QR CHECK-IN / CHECK-OUT

Implementasikan attendance menggunakan QR.

Flow:

```text
Volunteer
↓
Show QR / Scan QR
↓
Validate
↓
Check-in
↓
Working
↓
Check-out
```

Validasi:

- Event
- Assignment
- Shift
- Time window
- User
- Attendance status

Jangan menerima arbitrary user ID dari client.

---

# 20. ATTENDANCE ANTI-FRAUD

Pertimbangkan:

- Signed QR token
- Short-lived token
- Expiration
- Replay protection
- Server-side validation
- Rate limiting

Jangan menyimpan credential rahasia di QR secara plaintext.

---

# 21. ANNOUNCEMENT

Organizer dapat membuat announcement.

Target:

- All event volunteers
- Division
- Role
- Specific volunteer
- Specific shift

Announcement memiliki:

- Title
- Message
- Target
- Created by
- Published at
- Expiry

---

# 22. NOTIFICATION

Notification center:

- Registration submitted
- Registration accepted
- Registration rejected
- Role assignment
- Schedule change
- Announcement
- Event cancellation
- Shift reminder
- Attendance reminder
- Certificate available

Arsitektur harus memungkinkan integrasi:

- Email
- WhatsApp
- Push notification

Gunakan queue/background job untuk proses asynchronous.

---

# 23. ARTIST LIAISON MODULE

Khusus event musik, sediakan optional module.

Data artist:

- Artist name
- Arrival
- Departure
- Venue
- Hotel jika diperlukan
- Transport
- Requirements
- Assigned liaison
- Status

Liaison hanya dapat melihat data yang diperlukan untuk pekerjaannya.

Jangan expose data internal artist kepada seluruh volunteer.

---

# 24. INCIDENT MANAGEMENT

Sediakan incident reporting.

Contoh:

```text
Medical
Security
Crowd
Lost & Found
Technical
Other
```

Incident:

- Type
- Priority
- Location
- Description
- Attachment
- Reporter
- Assignee
- Status
- Timestamp

Priority:

```text
Low
Medium
High
Critical
```

Status:

```text
Open
Assigned
In Progress
Resolved
Closed
```

---

# 25. LOST & FOUND

Volunteer atau staff dapat melaporkan:

- Lost item
- Found item
- Location
- Time
- Description
- Photo
- Status

Data harus dibatasi berdasarkan event.

---

# 26. VOLUNTEER REPUTATION

Simpan event history.

Contoh:

```text
Events Joined: 12
Attendance Rate: 96%
Completed Events: 10
Average Rating: 4.8
```

Gunakan rating hanya untuk kebutuhan yang sah dan hindari expose informasi pribadi secara berlebihan.

---

# 27. VOLUNTEER TALENT POOL

Organizer dapat mencari volunteer berdasarkan:

- Experience
- Skill
- Role
- Event history
- Availability
- Attendance
- Rating

Namun organizer hanya boleh melihat informasi volunteer yang memang diizinkan oleh privacy rules.

Implementasikan:

- Profile visibility
- Data minimization
- Consent jika diperlukan

---

# 28. CERTIFICATE SYSTEM

Setelah event selesai:

```text
Event Completed
↓
Validate attendance
↓
Generate certificate
↓
Unique certificate ID
↓
QR verification
```

Public verification page:

```text
Certificate Verified
Event
Volunteer
Role
Completion date
```

Jangan expose informasi sensitif yang tidak diperlukan.

---

# 29. REPORTING

Organizer dashboard harus menampilkan:

### Recruitment

- Total registration
- Pending
- Accepted
- Rejected
- Waitlisted

### Capacity

- Total quota
- Filled quota
- Available quota

### Attendance

- Present
- Late
- Absent
- Completion rate

### Division

- Registration
- Stage
- Security
- Artist Liaison
- Documentation
- Technical

### Event Performance

- Total applicants
- Acceptance rate
- Attendance rate
- Role fill rate

Gunakan server-side aggregation untuk data besar.

---

# 30. EXPORT

Organizer dapat export data sesuai permission:

- CSV
- XLSX
- PDF

Export harus:

- Authorization checked
- Event scoped
- Logged
- Rate limited

Jangan izinkan export seluruh database tanpa authorization.

---

# 31. PUBLIC EVENT PAGE

Setiap event memiliki public URL:

```text
/events/{slug}
```

Public page menampilkan:

- Banner
- Description
- Venue
- Date
- Schedule
- Available roles
- Requirements
- Organizer information
- Sponsor
- Registration CTA

Jangan tampilkan:

- Email volunteer
- Nomor telepon volunteer
- Internal notes
- Internal organizer data
- Candidate ranking
- Private assignment information

---

# 32. SEARCH

Event search:

- Keyword
- Category
- City
- Date
- Organizer
- Availability
- Status

Gunakan pagination.

Jangan load seluruh database ke browser.

---

# 33. MULTI-TENANT SECURITY

Ini merupakan requirement paling penting.

Semua request harus mengikuti:

```text
User
↓
Organization
↓
Event
↓
Resource
```

Contoh:

```text
Organizer A
└── Event A

Organizer B
└── Event B
```

Organizer A tidak boleh:

```text
GET /events/B
GET /registrations/B
GET /assignments/B
GET /attendance/B
```

meskipun mengetahui ID.

Implementasikan:

- Tenant scoping
- Ownership checks
- Policy / authorization
- Query scoping
- Resource binding
- Backend validation

Jangan hanya menyembunyikan data di frontend.

---

# 34. IDOR PROTECTION

Test:

```text
User A
GET /api/events/123

change ID:

GET /api/events/124
```

Jika Event 124 bukan milik user tersebut:

```text
403 Forbidden
```

atau response yang tidak membocorkan keberadaan resource sesuai policy.

Lakukan hal yang sama pada:

- Registration
- Volunteer
- Assignment
- Schedule
- Attendance
- Certificate
- Incident
- Announcement
- Export

---

# 35. AUTHENTICATION SECURITY

Implementasikan:

- Secure registration
- Login
- Logout
- Forgot password
- Reset password
- Email verification
- Session management
- Password hashing
- Session expiration
- Re-authentication untuk operasi sensitif

Password tidak boleh:

- plaintext
- reversible encryption
- muncul di log
- muncul di API response

---

# 36. BRUTE FORCE PROTECTION

Untuk endpoint sensitif:

- Login
- Password reset
- OTP
- Verification
- API authentication

Terapkan:

- Rate limit
- Temporary lockout
- Monitoring
- Suspicious activity detection

Response jangan membocorkan apakah email/account tertentu terdaftar.

---

# 37. SESSION SECURITY

Gunakan:

- Secure cookies
- HttpOnly
- SameSite
- Session rotation
- Expiration
- Logout invalidation

Hindari menyimpan sensitive authentication token di localStorage jika arsitektur tidak membutuhkannya.

---

# 38. CSRF

Semua request state-changing harus terlindungi dari CSRF sesuai arsitektur authentication.

Test:

- Create
- Update
- Delete
- Accept
- Reject
- Assign
- Check-in

---

# 39. XSS PROTECTION

Protect against:

- Stored XSS
- Reflected XSS
- DOM XSS

Semua user-generated content harus di-escape sesuai konteks.

Jangan merender arbitrary HTML dari input user.

Jika rich text diperlukan:

- gunakan allowlist sanitizer
- strip dangerous HTML
- strip scripts
- strip event handlers
- sanitize URL scheme

---

# 40. SQL INJECTION

Jangan membuat raw SQL dari input user secara langsung.

Gunakan:

- Parameterized queries
- ORM
- Query builder

Jika raw SQL memang diperlukan, semua parameter harus bound.

---

# 41. MASS ASSIGNMENT

Jangan biarkan user mengirim:

```json
{
  "role": "super_admin",
  "organization_id": 123,
  "is_verified": true
}
```

lalu field tersebut otomatis diterapkan.

Gunakan:

- Explicit allowlist
- DTO / Request validation
- Server-controlled fields

---

# 42. PRIVILEGE ESCALATION

User tidak boleh dapat mengubah:

- role sendiri
- organization sendiri
- owner
- permission
- event ownership
- tenant ID
- security flags

kecuali melalui workflow resmi yang di-authorize.

---

# 43. FILE UPLOAD SECURITY

Untuk file upload:

- MIME validation
- Extension validation
- File signature validation
- Max file size
- Random generated filename
- Safe storage
- Non-executable storage
- Antivirus scanning bila diperlukan
- Image reprocessing bila diperlukan
- Prevent path traversal
- Prevent arbitrary file overwrite

Jangan menggunakan original filename sebagai path internal.

---

# 44. SECURITY HEADERS

Production environment harus memiliki security headers yang sesuai:

- Content-Security-Policy
- Strict-Transport-Security
- X-Content-Type-Options
- Referrer-Policy
- Permissions-Policy
- Frame protection
- Secure cookie configuration

Jangan menambahkan CSP secara asal; sesuaikan dengan asset/API yang benar-benar digunakan.

---

# 45. API SECURITY

API harus menggunakan:

- Authentication middleware
- Authorization middleware
- Input validation
- Rate limiting
- Pagination
- Resource scoping
- Consistent HTTP status code
- Safe error response

Jangan expose:

- stack trace
- SQL error
- environment variables
- server path
- secret
- internal token
- password hash

---

# 46. MASS REQUEST / ABUSE PROTECTION

Perhatikan:

- Registration spam
- Login spam
- OTP spam
- Export abuse
- Notification spam
- File upload abuse

Gunakan:

- Rate limiting
- Queue
- CAPTCHA bila diperlukan
- Request size limit
- Pagination
- Abuse monitoring

---

# 47. SSRF PROTECTION

Jika platform mengambil external URL:

- URL allowlist sesuai kebutuhan
- Validate protocol
- Block internal IP ranges
- Block localhost
- Block metadata endpoints
- Limit redirects
- Validate DNS resolution sesuai arsitektur

Jangan melakukan server-side fetch terhadap arbitrary URL dari user tanpa validasi.

---

# 48. URL / REDIRECT SECURITY

Jangan menggunakan redirect URL mentah dari user.

Cegah:

- Open redirect
- javascript:
- data:
- file:
- arbitrary protocol

Gunakan allowlist domain atau internal route.

---

# 49. ERROR HANDLING

Production response harus aman.

Jangan menampilkan:

```text
SQLSTATE
stack trace
server directory
database name
environment value
secret
```

Gunakan:

```text
Generic user-facing message
+
Internal error ID
+
Server-side logging
```

Buat:

- 401
- 403
- 404
- 419 / CSRF
- 422
- 429
- 500

---

# 50. DATABASE SECURITY

Gunakan:

- Foreign keys
- Unique constraints
- Indexes
- Check constraints bila tersedia
- Transactions
- Referential integrity

Minimal tables:

```text
users
organizations
organization_members
roles
permissions
role_permissions
user_roles

events
event_divisions
event_roles
event_shifts
event_custom_fields
event_custom_field_options

volunteer_profiles
registrations
registration_answers
registration_status_histories

assignments
attendance
attendance_logs

announcements
notifications

incidents
lost_found_items

certificates
certificate_verifications

audit_logs
security_logs
```

---

# 51. DATABASE OWNERSHIP

Semua resource penting harus dapat ditelusuri ke tenant/event.

Contoh:

```text
registration
→ event_id
→ event.organization_id
```

Hindari resource penting yang kehilangan hubungan tenant.

Gunakan foreign key.

---

# 52. TRANSACTIONAL INTEGRITY

Gunakan transaction untuk:

- Accept registration
- Assign role
- Update quota
- Registration cancellation
- Attendance check-in
- Attendance check-out
- Certificate generation state
- Bulk assignment

Jika salah satu langkah gagal, state harus rollback dengan benar.

---

# 53. IDEMPOTENCY

Untuk request tertentu seperti:

- Registration submit
- Check-in
- Check-out
- Payment jika ditambahkan
- Notification dispatch

gunakan idempotency mechanism jika diperlukan.

Double click tidak boleh menciptakan dua registration.

---

# 54. AUDIT LOG

Catat aktivitas penting:

```text
login
logout
create_event
update_event
delete_event
create_role
update_role
registration_created
registration_accepted
registration_rejected
assignment_created
assignment_updated
attendance_created
user_role_changed
permission_changed
export_created
security_event
```

Audit log berisi:

- Actor
- Organization
- Event
- Action
- Resource type
- Resource ID
- Old value
- New value
- IP
- User agent
- Timestamp
- Request ID

Jangan log password, token, secret, atau data sensitif secara berlebihan.

Audit log tidak boleh diubah oleh organizer.

---

# 55. SECURITY LOG

Pisahkan audit business activity dengan security events.

Security event contoh:

- Failed login
- Account lock
- Invalid token
- Forbidden access
- Rate limit exceeded
- Suspicious request
- File upload rejected
- CSRF violation

---

# 56. PRIVACY

Gunakan principle of least privilege.

Volunteer profile harus memiliki visibility rules.

Jangan memperlihatkan:

- phone number
- personal email
- emergency contact
- private notes

ke organizer/member yang tidak membutuhkan data tersebut.

Kumpulkan data seminimal mungkin.

Sediakan mekanisme untuk data retention/deletion sesuai kebutuhan platform dan regulasi yang berlaku.

---

# 57. DATA RETENTION

Buat kebijakan retention yang configurable.

Contoh:

- Registration history
- Attendance
- Audit log
- Deleted accounts
- Uploaded files

Jangan langsung hard delete data penting yang dibutuhkan audit.

Gunakan soft delete atau archival sesuai kebutuhan.

---

# 58. PERFORMANCE

Aplikasi harus tetap performant saat:

- 100 event
- 1,000 event
- 10,000 event
- 100,000 volunteer
- 1,000,000 registration

Gunakan:

- Pagination
- Indexing
- Query optimization
- Eager loading
- Caching
- Queue
- Background job
- Aggregation
- Lazy loading sesuai kebutuhan

Deteksi dan hilangkan N+1 query.

---

# 59. FRONTEND SECURITY

Frontend bukan security boundary.

Semua:

- permission
- authorization
- quota
- ownership
- status transition

harus divalidasi backend.

UI hanya mencerminkan permission yang sudah diberikan.

---

# 60. ACCESSIBILITY

Target minimal:

- Keyboard navigation
- Semantic HTML
- Form labels
- Focus state
- Error message
- Accessible modal
- Color contrast
- Screen reader compatibility

---

# 61. RESPONSIVE DESIGN

Support:

- Mobile
- Tablet
- Desktop

Prioritaskan mobile untuk volunteer.

Organizer dashboard dapat dioptimalkan untuk desktop tetapi tetap usable pada tablet/mobile.

---

# 62. UX

Setiap form harus memiliki:

- Loading state
- Validation state
- Success state
- Error state
- Empty state
- Disabled state
- Confirmation untuk destructive action

Cegah double submit.

Tetapi tetap lakukan protection backend.

---

# 63. QUEUE / BACKGROUND JOB

Gunakan background processing untuk:

- Email
- WhatsApp notification
- Certificate generation
- Large export
- Bulk notification
- Image processing
- Report generation

User tidak perlu menunggu proses berat selesai dalam HTTP request.

---

# 64. OBSERVABILITY

Implementasikan:

- Application logs
- Security logs
- Audit logs
- Request ID
- Error tracking
- Queue monitoring
- Performance monitoring

Jangan memasukkan secret ke logs.

---

# 65. TESTING

Buat automated tests.

## Unit Test

Test:

- Quota calculation
- Registration rules
- Status transition
- Permission
- Assignment logic
- Attendance logic

## Feature / Integration Test

Test:

- Register
- Login
- Create event
- Create role
- Registration
- Accept
- Reject
- Assign
- Check-in
- Check-out
- Certificate

## Authorization Test

Test setiap role:

```text
Super Admin
Organizer Owner
Organizer Staff
Volunteer
Guest
```

Pastikan setiap endpoint memiliki expected authorization.

---

# 66. SECURITY TESTING

Secara khusus test:

```text
IDOR
Privilege escalation
Mass assignment
SQL injection
XSS
CSRF
SSRF
Open redirect
File upload vulnerability
Brute force
Rate limiting
Session invalidation
Duplicate registration
Race condition
Broken access control
Tenant isolation
Information disclosure
```

Jangan hanya menguji happy path.

---

# 67. TENANT ISOLATION TEST

Wajib membuat test:

```text
Organizer A
Event A

Organizer B
Event B
```

Pastikan:

```text
Organizer A cannot read Event B
Organizer A cannot modify Event B
Organizer A cannot delete Event B
Organizer A cannot read Registration B
Organizer A cannot export Event B
Organizer A cannot manipulate Attendance B
```

Ini merupakan security-critical test.

---

# 68. END-TO-END TEST

### Volunteer flow

```text
Register
→ Verify email
→ Login
→ Browse event
→ Select role
→ Submit registration
→ Check status
→ Receive acceptance
→ View schedule
→ Check-in
→ Check-out
→ Receive certificate
```

### Organizer flow

```text
Login
→ Create event
→ Create divisions
→ Create roles
→ Create shifts
→ Open registration
→ Review candidates
→ Accept
→ Assign
→ Monitor attendance
→ Close event
→ Generate report
```

### Admin flow

```text
Login
→ Manage organizers
→ Manage users
→ Manage permissions
→ Review logs
→ Suspend user
→ Audit actions
```

---

# 69. TEST EDGE CASES

Test:

- Event deleted
- Event cancelled
- Registration closed
- Role disabled
- Role quota full
- Volunteer already registered
- Volunteer withdraws
- Organizer suspended
- User suspended
- Session expired
- Invalid token
- Expired QR
- Duplicate QR
- Double submit
- Concurrent registration
- Concurrent acceptance
- Large export
- Invalid file
- Large file
- Malicious file
- Unauthorized API request

---

# 70. DEPENDENCY SECURITY

Before production:

- Dependency audit
- Remove unused dependencies
- Update vulnerable packages
- Lock dependencies
- Review transitive dependencies
- Run static analysis
- Run lint
- Run type checks jika digunakan
- Run test suite

Jangan mengabaikan critical/high security vulnerability tanpa documented reason.

---

# 71. SECRETS

Jangan pernah commit:

```text
.env
database password
API key
JWT secret
OAuth secret
private key
SMTP password
webhook secret
cloud credentials
```

Gunakan environment variables / secret management.

---

# 72. PRODUCTION HARDENING

Production:

```text
DEBUG = false
HTTPS = required
Secure cookies = enabled
Rate limiting = enabled
Logging = enabled
Error tracking = enabled
Backups = enabled
Database credentials = protected
Secrets = externalized
```

Jangan menggunakan development credentials di production.

---

# 73. BACKUP & RECOVERY

Siapkan:

- Database backup
- Backup schedule
- Retention policy
- Recovery procedure
- Restore testing

Backup tidak dianggap valid sampai dapat diuji restore.

---

# 74. DATABASE MIGRATION

Semua perubahan schema harus menggunakan migration.

Jangan mengedit production database secara manual tanpa documented migration.

Migration harus:

- Reversible bila memungkinkan
- Tested
- Backward compatible untuk deployment yang membutuhkan rolling update

---

# 75. API DESIGN

Gunakan struktur:

```text
/api/auth/*
/api/organizations/*
/api/events/*
/api/events/{event}/divisions/*
/api/events/{event}/roles/*
/api/events/{event}/shifts/*
/api/events/{event}/registrations/*
/api/events/{event}/assignments/*
/api/events/{event}/attendance/*
/api/notifications/*
/api/reports/*
/api/admin/*
```

Semua endpoint harus memiliki:

```text
Authentication
Authorization
Validation
Tenant scope
Event scope
Rate limit sesuai kebutuhan
```

---

# 76. RESOURCE OWNERSHIP

Contoh:

```text
Organization
    ↓
Event
    ↓
Registration
    ↓
Assignment
    ↓
Attendance
```

Saat membaca atau mengubah resource, backend harus melakukan ownership chain validation.

Jangan hanya:

```text
find(resource_id)
```

tetapi lakukan scoped query sesuai tenant/event.

---

# 77. PUBLIC / PRIVATE DATA SEPARATION

Pisahkan:

### Public

- Event information
- Venue
- Schedule yang memang dipublikasikan
- Organizer profile
- Public roles

### Private

- Volunteer data
- Candidate notes
- Organizer internal notes
- Internal schedule
- Emergency contacts
- Security incidents

Tidak boleh terjadi accidental exposure.

---

# 78. ADMIN SAFETY

Untuk operasi sangat sensitif:

- Change permission
- Delete organization
- Suspend account
- Impersonation jika nanti dibuat
- Export sensitive data

pertimbangkan:

- Re-authentication
- Confirmation
- Audit
- Reason logging

---

# 79. DATABASE CONSTRAINTS

Gunakan constraint untuk menjaga data.

Contoh:

```text
unique(user_id, event_id)
```

untuk mencegah duplicate registration.

Tambahkan constraint yang sesuai untuk:

- assignment
- attendance
- event slug
- organization slug
- certificate number

---

# 80. BUSINESS RULES

Business rules harus berada di service/domain layer.

Hindari menaruh semua logic di controller.

Gunakan service seperti:

```text
EventService
RegistrationService
QuotaService
AssignmentService
AttendanceService
CertificateService
NotificationService
AuditLogService
SecurityService
```

Controller harus tetap tipis.

---

# 81. DOCUMENTATION

Buat:

```text
README.md
PRD.md
ARCHITECTURE.md
DATABASE.md
SECURITY.md
API.md
TESTING.md
DEPLOYMENT.md
```

Dokumentasikan:

- Architecture
- Database
- Authentication
- Authorization
- Tenant isolation
- API
- Security
- Deployment
- Backup
- Testing
- Troubleshooting

---

# 82. DEVELOPMENT WORKFLOW

Jangan langsung menulis seluruh aplikasi.

Urutan:

```text
1. Requirement analysis
2. PRD
3. Architecture
4. Threat model
5. Database design
6. Authorization matrix
7. API design
8. Authentication
9. Multi-tenant foundation
10. Organization
11. Event
12. Division
13. Role
14. Registration
15. Quota
16. Assignment
17. Schedule
18. Attendance
19. Notification
20. Certificate
21. Reporting
22. Frontend refinement
23. Automated testing
24. Security testing
25. Performance testing
26. Production hardening
```

Jangan membangun UI terlebih dahulu sebelum tenancy, authentication, authorization, dan database model jelas.

---

# 83. AUTHORIZATION MATRIX

Sebelum implementasi, buat tabel permission:

| Action | Super Admin | Organizer Owner | Organizer Staff | Volunteer | Guest |
|---|---|---|---|---|---|
| Create Organization | ✓ | - | - | - | - |
| Create Event | ✓ | ✓ | sesuai permission | - | - |
| Edit Event | ✓ | ✓ | sesuai permission | - | - |
| Delete Event | ✓ | ✓ | - | - | - |
| View Registration | ✓ | ✓ | sesuai permission | own only | - |
| Accept Volunteer | ✓ | ✓ | sesuai permission | - | - |
| Assign Volunteer | ✓ | ✓ | sesuai permission | - | - |
| Check-in | ✓ | ✓ | ✓ | own only | - |
| View Audit Log | ✓ | sesuai permission | - | - | - |

Buat authorization matrix lengkap sebelum coding.

---

# 84. THREAT MODEL

Identifikasi minimal:

```text
Broken Access Control
Tenant Data Leakage
Credential Theft
Brute Force
XSS
CSRF
SQL Injection
SSRF
File Upload Abuse
Spam
Race Condition
Data Exposure
Session Hijacking
Privilege Escalation
API Abuse
```

Untuk setiap threat:

```text
Threat
Impact
Likelihood
Mitigation
Test
```

---

# 85. DEFINITION OF DONE

Project hanya dianggap selesai jika:

### Functional

- Multi-organizer
- Multi-event
- Multi-role
- Multi-user
- Registration
- Selection
- Assignment
- Shift
- Attendance
- Notification
- Certificate
- Reporting

### Security

- Authentication secure
- RBAC
- Permission system
- Tenant isolation
- Event ownership
- IDOR protection
- Privilege escalation protection
- CSRF protection
- XSS protection
- SQL injection protection
- SSRF protection
- Rate limiting
- File upload security
- Session security
- Secure headers
- Audit logging
- Security logging

### Data Integrity

- Foreign keys
- Unique constraints
- Transactions
- Race condition protection
- Idempotency
- Duplicate protection

### Quality

- Unit tests
- Integration tests
- Authorization tests
- Security tests
- E2E tests
- Static analysis
- Dependency audit
- Performance review
- N+1 review

### Production

- DEBUG disabled
- HTTPS
- Secure cookies
- Secrets protected
- Backup
- Restore test
- Monitoring
- Error tracking
- Documentation

---

# 86. FINAL DEVELOPMENT RULES

Ikuti prinsip:

```text
Security First
↓
Authorization First
↓
Tenant Isolation
↓
Server-side Validation
↓
Database Integrity
↓
Transaction & Concurrency Safety
↓
Business Logic
↓
Performance
↓
UX
↓
Testing
```

Jangan pernah menganggap fitur aman hanya karena:

- tombol disembunyikan
- route tidak ditampilkan
- frontend melakukan validation
- ID sulit ditebak
- user menggunakan UUID
- form memiliki CAPTCHA

Security harus ditegakkan di backend.

Jangan percaya data dari client.

Jangan percaya role yang dikirim client.

Jangan percaya tenant ID yang dikirim client.

Jangan percaya event ID yang dikirim client.

Semua harus diverifikasi dari authenticated server context.

Setiap perubahan penting harus memiliki test.

Setiap bug harus diperbaiki pada root cause, bukan dengan workaround sementara.

Setiap fitur baru harus dievaluasi terhadap:

**Authentication → Authorization → Tenant Isolation → Validation → Data Integrity → Concurrency → Privacy → Abuse Prevention → Logging → Testing**

Target akhir adalah:

**Production-ready multi-event platform yang secure-by-design, tenant-isolated, scalable, maintainable, responsive, tested, dan siap digunakan oleh banyak organizer serta event secara bersamaan.**

Jangan menyatakan sistem "100% bebas bug" atau "100% aman". Sebagai gantinya, lakukan pengujian, hardening, security review, dan dokumentasikan risiko yang masih tersisa.