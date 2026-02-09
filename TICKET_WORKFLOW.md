# ServiceLink Ticket Workflow System

## Complete Status List

All 8 ticket statuses available in the system:
1. **new** - Newly created ticket
2. **pending** - Waiting for information/approval
3. **assigned** - Assigned to staff member
4. **in_progress** - Staff actively working on it
5. **on_hold** - Temporarily paused
6. **resolved** - Issue fixed and documented
7. **closed** - Ticket completed and finalized
8. **reopen** - Reopened after closure

---

## Role-Based Status Management

### **Department Admin** 
**Permissions:**
- ✅ View all department tickets
- ✅ Track and monitor progress  
- ✅ Assign & reassign tickets to department staff
- ✅ Update **administrative statuses only**:
  - `new` - New ticket
  - `pending` - Waiting for information/approval
  - `assigned` - Assigned to staff
  - `on_hold` - Temporarily paused
  - `reopen` - Reopened after closure
- ✅ Add notes to status updates
- ✅ View all attachments (initial + proof of work)
- ❌ Cannot update operational statuses (in_progress, resolved, closed)
- ❌ Cannot resolve tickets unless personally worked on them

**Use Case:** Department admins manage ticket flow, assign work, and handle administrative decisions.

---

### **Staff**
**Permissions:**
- ✅ View assigned tickets
- ✅ Handle the actual work
- ✅ Update **operational statuses only**:
  - `in_progress` - Currently working on it
  - `resolved` - Issue fixed (requires resolution details)
  - `closed` - Ticket completed
- ✅ **Upload proof of work multiple times** (photos, videos, documents)
  - Files are ADDED, not replaced
  - Can upload at any stage of work
  - Each upload is tracked separately
- ✅ Add work notes to status updates
- ✅ Required to provide resolution details when marking as resolved
- ✅ View all attachments (initial + proof of work)
- ❌ Cannot update administrative statuses (new, pending, assigned, on_hold, reopen)
- ❌ Cannot reassign tickets

**Use Case:** Staff members do the actual service work and document their progress with proof.

---

### **Admin**
**Permissions:**
- ✅ View all tickets across all departments
- ✅ Track ticket history and status changes
- ✅ Monitor system-wide progress
- ✅ View all attachments and proof of work from all tickets
- ❌ Cannot edit tickets
- ❌ Cannot update statuses
- ❌ Cannot assign tickets

**Use Case:** Admins have oversight for reporting and monitoring only.

---

### **Student (Requester)**
**Permissions:**
- ✅ View their own tickets
- ✅ See all status updates and comments
- ✅ View all proof of work uploaded by staff
- ✅ Track progress of their requests
- ✅ Add comments to their tickets

**Use Case:** Students can monitor the progress and see evidence of work being done.

---

## Proof of Work System

### **Multiple Upload Feature:**
- Staff can upload proof of work **multiple times**
- Each upload is **added** to the ticket, not replaced
- Previous uploads remain visible
- All uploads are timestamped and attributed to the uploader

### **When to Upload:**
Staff should upload proof of work when:
1. Starting work (`in_progress`) - Initial assessment photos
2. During work - Progress documentation
3. Completing work (`resolved`) - Final result photos/videos
4. Any significant milestone

### **Accepted File Types:**
- **Images:** JPG, PNG, GIF
- **Videos:** MP4, MPEG
- **Documents:** PDF, DOC, DOCX

### **Limits:**
- Maximum 5 files **per upload** (can upload multiple times)
- Maximum 10MB per file
- Files stored in: `uploads/proof_of_work/`

### **Visibility:**
- **Everyone can see all attachments:**
  - Admin: Can view all proof of work across all tickets
  - Department Admin: Can view all proof of work in their department
  - Staff: Can view all proof of work on tickets they access
  - Student: Can view all proof of work on their own tickets

### **Display:**
- **Initial Attachments:** Files uploaded by requester when creating ticket
- **Proof of Work:** Files uploaded by staff during service work (shown with green border)
- Each proof file shows: uploader name, upload date, file size
- Sorted by most recent first

---

## Status Flow

```
NEW (created by student)
  ↓
PENDING (dept admin - waiting for info/approval)
  ↓
ASSIGNED (dept admin - assigned to staff)
  ↓
IN PROGRESS (staff - actively working)
  ↓  ↑ (staff can upload proof multiple times)
  ↓  ↑
RESOLVED (staff - work completed + proof uploaded)
  ↓
CLOSED (staff - ticket finalized)

Special:
ON HOLD (dept admin - temporarily paused)
REOPEN (dept admin - reopened after closure)
```

---

## Key Features

1. **Role Separation:** Clear division between administrative and operational tasks
2. **Multiple Uploads:** Staff can upload proof of work multiple times throughout the ticket lifecycle
3. **Accountability:** Staff must document their work with proof attachments
4. **Transparency:** All status changes logged with notes and timestamps
5. **Validation:** System enforces role-based status restrictions
6. **Evidence:** Visual proof of service completion for quality assurance
7. **Universal Visibility:** Everyone can see proof of work (admin, dept admin, staff, student)

---

## Database Changes

### Updated Tables:
- `ticket_status_history.notes` - Stores work notes and update reasons
- `ticket_attachments.uploaded_by` - Tracks who uploaded each file
- New folder: `uploads/proof_of_work/` - Stores staff work documentation

### Queries Updated:
- Attachments now separated by uploader (requester vs staff)
- Status updates validate allowed statuses per role
- File uploads linked to status changes
- Multiple uploads supported (additive, not replacement)
