# Complete UI Implementation Guide

## Overview
This guide provides the exact code replacements needed to update admin, department, and staff view pages to match the student UI layout.

## Implementation Order
1. Staff view.php (most complex)
2. Department view.php (similar to staff)
3. Admin view.php (simplest)

---

## STAFF VIEW.PHP CHANGES

### Step 1: Find the main content row (around line 260)
**FIND THIS:**
```php
<!-- Ticket Details -->
<div class="row">
    <div class="col-lg-8 mb-4">
```

**REPLACE WITH:**
```php
<div class="row">
    <!-- Main Ticket Details -->
    <div class="col-lg-8 mb-4">
        <!-- Ticket Header -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="flex-grow-1">
                        <h3 class="mb-2"><?php echo htmlspecialchars($ticket['title']); ?></h3>
                        <div class="d-flex gap-2 flex-wrap mb-3">
                            <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                            </span>
                            <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                <?php echo ucfirst($ticket['priority']); ?> Priority
                            </span>
                            <?php if ($ticket['category_name']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($ticket['category_name']); ?></span>
                            <?php endif; ?>
                            <?php if ($ticket['subcategory_name']): ?>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($ticket['subcategory_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Description</h6>
                        <p class="mb-3"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Details</h6>
                        <div class="small">
                            <div class="mb-1"><strong>Ticket #:</strong> <?php echo htmlspecialchars($ticket['ticket_number']); ?></div>
                            <div class="mb-1"><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></div>
                            <?php if ($ticket['location_name']): ?>
                                <div class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($ticket['location_name']); ?></div>
                            <?php endif; ?>
                            <?php if ($ticket['department_name']): ?>
                                <div class="mb-1"><strong>Department:</strong> <?php echo htmlspecialchars($ticket['department_name']); ?></div>
                            <?php endif; ?>
                            <?php if ($ticket['assigned_staff_name']): ?>
                                <div class="mb-1"><strong>Assigned to:</strong> <?php echo htmlspecialchars($ticket['assigned_staff_name']); ?></div>
                            <?php endif; ?>
                            <?php if ($ticket['resolved_at']): ?>
                                <div class="mb-1"><strong>Resolved:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['resolved_at'])); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($ticket['resolution']): ?>
                    <div class="mt-3 pt-3 border-top">
                        <h6 class="text-success mb-2"><i class="fas fa-check-circle me-2"></i>Resolution</h6>
                        <div class="alert alert-success mb-0">
                            <?php echo nl2br(htmlspecialchars($ticket['resolution'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
```

### Step 2: Update Attachments Section
Keep the existing Initial Attachments and Proof of Work sections as they are (they're already good).

### Step 3: Update Comments Section
**FIND:**
```php
<!-- Comments Section -->
<div class="card shadow mt-4">
```

**REPLACE WITH:**
```php
<!-- Comments Section -->
<div class="card border-0 shadow-sm">
```

### Step 4: Update Right Sidebar (col-lg-4)
**FIND:**
```php
<!-- Sidebar Actions -->
<div class="col-lg-4">
```

**REPLACE ENTIRE SIDEBAR WITH:**
```php
<!-- Sidebar -->
<div class="col-lg-4">
    <!-- Ticket History -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light border-0">
            <h6 class="card-title mb-0 text-dark">
                <i class="fas fa-history me-2"></i>
                Ticket History
            </h6>
        </div>
        <div class="card-body">
            <?php
            // Get ticket history
            try {
                $stmt = $pdo->prepare("
                    SELECT tsh.*, CONCAT(u.first_name, ' ', u.last_name) as changed_by_name
                    FROM ticket_status_history tsh
                    LEFT JOIN users u ON tsh.changed_by = u.id
                    WHERE tsh.ticket_id = ?
                    ORDER BY tsh.created_at DESC
                    LIMIT 10
                ");
                $stmt->execute([$ticket_id]);
                $history = $stmt->fetchAll();
                
                if (empty($history)): ?>
                    <p class="text-muted text-center small">No status changes yet.</p>
                <?php else: ?>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($history as $entry): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex gap-2 mb-1">
                                    <span class="badge bg-<?php echo getStatusColor($entry['old_status']); ?>" style="font-size: 0.7rem;">
                                        <?php echo ucfirst(str_replace('_', ' ', $entry['old_status'])); ?>
                                    </span>
                                    <i class="fas fa-arrow-right text-muted" style="font-size: 0.7rem; margin-top: 4px;"></i>
                                    <span class="badge bg-<?php echo getStatusColor($entry['new_status']); ?>" style="font-size: 0.7rem;">
                                        <?php echo ucfirst(str_replace('_', ' ', $entry['new_status'])); ?>
                                    </span>
                                </div>
                                <?php if ($entry['changed_by_name']): ?>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($entry['changed_by_name']); ?>
                                    </small>
                                <?php endif; ?>
                                <?php if ($entry['notes']): ?>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-sticky-note me-1"></i>
                                        <?php echo htmlspecialchars($entry['notes']); ?>
                                    </small>
                                <?php endif; ?>
                                <small class="text-muted d-block">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('M j, Y g:i A', strtotime($entry['created_at'])); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif;
            } catch (PDOException $e) {
                echo '<p class="text-muted small">Unable to load history.</p>';
            }
            ?>
        </div>
    </div>

    <!-- Status Update & Work Progress -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light border-0">
            <h6 class="card-title mb-0 text-dark">
                <i class="fas fa-tasks me-2"></i>
                Update Work Progress
            </h6>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="status" class="form-label">Status (Operational)</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="" disabled>── Administrative (Dept Admin) ──</option>
                        <option value="new" disabled>New</option>
                        <option value="pending" disabled>Pending</option>
                        <option value="assigned" disabled>Assigned</option>
                        <option value="on_hold" disabled>On Hold</option>
                        <option value="reopen" disabled>Reopen</option>
                        <option value="" disabled>── Operational (You) ──</option>
                        <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $ticket['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="closed" <?php echo $ticket['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                    <small class="text-muted">You handle the actual service work</small>
                </div>
                
                <div class="mb-3" id="resolution-field" style="display: none;">
                    <label for="resolution" class="form-label">Resolution Details <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="resolution" name="resolution" rows="3" placeholder="Describe how you resolved this issue..."><?php echo htmlspecialchars($ticket['resolution'] ?? ''); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="work_notes" class="form-label">Work Notes</label>
                    <textarea class="form-control" id="work_notes" name="work_notes" rows="2" placeholder="Describe what you did..."></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-paperclip me-1"></i>
                        Proof of Work (Optional)
                    </label>
                    <input type="file" class="form-control" name="proof_files[]" id="proof_files" multiple accept="image/*,video/*,.pdf,.doc,.docx">
                    <small class="text-muted d-block mt-1">
                        <i class="fas fa-info-circle me-1"></i>
                        You can upload multiple times - files will be added, not replaced
                    </small>
                    <small class="text-muted">Max 5 files per upload, 10MB each</small>
                </div>
                
                <div id="file-preview" class="mb-3"></div>
                
                <button type="submit" name="update_status" class="btn btn-success w-100">
                    <i class="fas fa-save me-1"></i>
                    Update Progress
                </button>
            </form>
        </div>
    </div>

    <!-- Requester Information -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light border-0">
            <h6 class="card-title mb-0 text-dark">
                <i class="fas fa-user me-2"></i>
                Requester Information
            </h6>
        </div>
        <div class="card-body">
            <div class="text-center mb-3">
                <?php if ($ticket['requester_profile']): ?>
                    <img src="../<?php echo htmlspecialchars($ticket['requester_profile']); ?>" 
                         alt="Profile" class="rounded-circle border border-3 border-success" 
                         style="width: 60px; height: 60px; object-fit: cover;">
                <?php else: ?>
                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center border border-3 border-success" 
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-user-graduate fa-lg text-success"></i>
                    </div>
                <?php endif; ?>
                <div class="mt-2">
                    <h6><?php echo htmlspecialchars($ticket['requester_name']); ?></h6>
                    <small class="text-muted">Student</small>
                </div>
            </div>
            
            <div class="small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Email:</span>
                    <span><?php echo htmlspecialchars($ticket['requester_email']); ?></span>
                </div>
                <?php if ($ticket['user_number']): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Student #:</span>
                        <span><?php echo htmlspecialchars($ticket['user_number']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($ticket['year_level']): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Year Level:</span>
                        <span><?php echo htmlspecialchars($ticket['year_level']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light border-0">
            <h6 class="card-title mb-0 text-dark">
                <i class="fas fa-bolt me-2"></i>
                Quick Actions
            </h6>
        </div>
        <div class="card-body">
            <div class="d-grid gap-2">
                <a href="tickets.php" class="btn btn-outline-success">
                    <i class="fas fa-list me-2"></i>
                    All Tickets
                </a>
                <button onclick="window.print()" class="btn btn-outline-secondary">
                    <i class="fas fa-print me-2"></i>
                    Print Ticket
                </button>
            </div>
        </div>
    </div>
</div>
```

---

## Summary

After making these changes:
1. **Backup your current files first!**
2. Apply changes to staff/view.php
3. Test thoroughly
4. Repeat similar changes for department/view.php and admin/view.php

The new UI will have:
- ✅ Clean student-like layout
- ✅ Ticket history at top of right sidebar
- ✅ Better visual hierarchy
- ✅ All functionality preserved
- ✅ Responsive design

Would you like me to provide the department and admin versions as well?
