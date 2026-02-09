# New View Layout Template

This template shows the new UI structure for admin, department, and staff view pages.

## Layout Structure:

```
┌─────────────────────────────────────────────────────────────┐
│ Header: Ticket #XXX                    [Back to Tickets]    │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────────────────┐  ┌────────────────────────┐  │
│  │  LEFT (col-lg-8)         │  │  RIGHT (col-lg-4)      │  │
│  │                          │  │                        │  │
│  │  1. Ticket Header Card   │  │  1. Ticket History    │  │
│  │     - Title              │  │     (Status changes)   │  │
│  │     - Status badges      │  │                        │  │
│  │     - Description        │  │  2. Update Form       │  │
│  │     - Details            │  │     (Staff/Dept)       │  │
│  │                          │  │                        │  │
│  │  2. Initial Attachments  │  │  3. Requester Info    │  │
│  │                          │  │                        │  │
│  │  3. Proof of Work        │  │  4. Quick Actions     │  │
│  │                          │  │                        │  │
│  │  4. Comments Section     │  │                        │  │
│  │                          │  │                        │  │
│  └──────────────────────────┘  └────────────────────────┘  │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

## Key Changes:

1. **Student-like layout** - Clean, card-based design
2. **Ticket History** - Moved to upper right sidebar (first card)
3. **Update forms** - In right sidebar (staff/dept specific)
4. **Attachments** - Separated into Initial + Proof of Work sections
5. **Comments** - At bottom of left column
6. **Responsive** - col-lg-8 / col-lg-4 split

## Files to Update:
- servicelink_web/staff/view.php
- servicelink_web/department/view.php  
- servicelink_web/admin/view.php
