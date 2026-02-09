# UI Update Instructions for Admin, Department, and Staff View Pages

## Overview
Update the ticket view pages to match the student UI layout with ticket history in the upper right sidebar.

## Changes Needed

### 1. Main Layout Structure
Change from current layout to:
- **Left Column (col-lg-8)**: Ticket header, attachments, comments
- **Right Column (col-lg-4)**: Ticket history (top), update forms, requester info, quick actions

### 2. Right Sidebar Order (Top to Bottom)

#### For STAFF:
1. **Ticket History Card** (NEW - move from bottom)
2. **Update Work Progress Card** (existing status update form)
3. **Requester Information Card** (existing)
4. **Quick Actions Card** (existing)

#### For DEPARTMENT:
1. **Ticket History Card** (NEW - move from bottom)
2. **Update Status Card** (existing assignment + status form)
3. **Requester Information Card** (existing)
4. **Quick Actions Card** (existing)

#### For ADMIN:
1. **Ticket History Card** (existing - already there)
2. **Requester Information Card** (existing)
3. **Quick Actions Card** (existing)

### 3. Ticket History Card HTML

```html
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
                <div class="timeline" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($history as $entry): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <div class="d-flex gap-2 mb-1">
                                <span class="badge bg-<?php echo getStatusColor($entry['old_status']); ?> badge-sm">
                                    <?php echo ucfirst(str_replace('_', ' ', $entry['old_status'])); ?>
                                </span>
                                <i class="fas fa-arrow-right text-muted" style="font-size: 0.7rem; margin-top: 4px;"></i>
                                <span class="badge bg-<?php echo getStatusColor($entry['new_status']); ?> badge-sm">
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
```

### 4. Ticket Header Card (Left Column - Top)

```html
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
                    <div class="mb-1">
                        <strong>Ticket #:</strong> <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                    </div>
                    <div class="mb-1">
                        <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                    </div>
                    <?php if ($ticket['location_name']): ?>
                        <div class="mb-1">
                            <strong>Location:</strong> <?php echo htmlspecialchars($ticket['location_name']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($ticket['department_name']): ?>
                        <div class="mb-1">
                            <strong>Department:</strong> <?php echo htmlspecialchars($ticket['department_name']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($ticket['assigned_staff_name']): ?>
                        <div class="mb-1">
                            <strong>Assigned to:</strong> <?php echo htmlspecialchars($ticket['assigned_staff_name']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($ticket['resolved_at']): ?>
                        <div class="mb-1">
                            <strong>Resolved:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['resolved_at'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($ticket['resolution']): ?>
            <div class="mt-3 pt-3 border-top">
                <h6 class="text-success mb-2">
                    <i class="fas fa-check-circle me-2"></i>
                    Resolution
                </h6>
                <div class="alert alert-success mb-0">
                    <?php echo nl2br(htmlspecialchars($ticket['resolution'])); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
```

### 5. Implementation Steps

1. **Backup current files** before making changes
2. **Update each view.php file** in this order:
   - staff/view.php
   - department/view.php
   - admin/view.php
3. **Test each file** after updating
4. **Verify responsive design** on mobile/tablet

### 6. CSS Additions (if needed)

Add to `assets/css/style.css`:

```css
.badge-sm {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.timeline {
    padding-left: 0;
}

.card-title {
    font-weight: 600;
}
```

## Summary

The new layout provides:
- ✅ Clean, modern UI matching student view
- ✅ Ticket history prominently displayed (upper right)
- ✅ Better organization of information
- ✅ Responsive design
- ✅ Consistent experience across all roles
