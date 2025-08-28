<?php
require_once '../includes/header.php';
restrictAccess(['admin']);

$stmt = $conn->query("
    SELECT 
        t.*, 
        (SELECT COUNT(*) FROM kurssit k WHERE k.tila_id = t.tunnus) AS course_count,
        GROUP_CONCAT(
            CONCAT(
                k.nimi, ' (', 
                o.etunimi, ' ', o.sukunimi, ', ', 
                k.alkupvm, ' - ', k.loppupvm, ', ', 
                (SELECT COUNT(*) FROM kirjautumiset ki WHERE ki.kurssi_id = k.tunnus), ' students)'
            ) SEPARATOR '; '
        ) AS course_details
    FROM tilat t
    LEFT JOIN kurssit k ON k.tila_id = t.tunnus
    LEFT JOIN opettajat o ON k.opettaja_id = o.tunnus
    GROUP BY t.tunnus
");
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nimi = $_POST['nimi'];
    $kapasiteetti = $_POST['kapasiteetti'];

    if ($id) {
        $stmt = $conn->prepare("UPDATE tilat SET nimi = ?, kapasiteetti = ? WHERE tunnus = ?");
        $stmt->execute([$nimi, $kapasiteetti, $id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO tilat (nimi, kapasiteetti) VALUES (?, ?)");
        $stmt->execute([$nimi, $kapasiteetti]);
    }
    header("Location: rooms.php");
    exit();
}

// Delete room
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM tilat WHERE tunnus = ?");
    $stmt->execute([$id]);
    header("Location: rooms.php");
    exit();
}
?>
<h2>Rooms</h2>
<!-- Add/Edit Modal -->
<button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#roomModal">Add Room</button>
<div class="modal fade" id="roomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add/Edit Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id" id="roomId">
                    <div class="mb-3">
                        <label for="nimi" class="form-label">Room Name</label>
                        <input type="text" class="form-control" id="nimi" name="nimi" required>
                    </div>
                    <div class="mb-3">
                        <label for="kapasiteetti" class="form-label">Capacity</label>
                        <input type="number" class="form-control" id="kapasiteetti" name="kapasiteetti" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Room List -->
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Name</th>
            <th>Capacity</th>
            <th>Courses</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rooms as $room): ?>
            <tr>
                <td><?php echo htmlspecialchars($room['nimi']); ?></td>
                <td>
                    <?php 
                    echo htmlspecialchars($room['kapasiteetti']);
                    // Check for overcapacity
                    $course_details = explode('; ', $room['course_details'] ?? '');
                    $overcapacity = false;
                    foreach ($course_details as $detail) {
                        if (preg_match('/(\d+) students\)$/', $detail, $matches)) {
                            if ((int)$matches[1] > $room['kapasiteetti']) {
                                $overcapacity = true;
                                break;
                            }
                        }
                    }
                    if ($overcapacity) {
                        echo ' <span class="warning-icon">⚠️</span>';
                    }
                    ?>
                </td>
                <td><?php echo htmlspecialchars($room['course_details'] ?? 'No courses'); ?></td>
                <td>
                    <button class="btn btn-sm btn-warning edit-room" 
                            data-id="<?php echo $room['tunnus']; ?>" 
                            data-nimi="<?php echo $room['nimi']; ?>" 
                            data-kapasiteetti="<?php echo $room['kapasiteetti']; ?>" 
                            data-bs-toggle="modal" 
                            data-bs-target="#roomModal">Edit</button>
                    <a href="?delete=<?php echo $room['tunnus']; ?>" 
                       class="btn btn-sm btn-danger" 
                       onclick="return confirm('Are you sure?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.edit-room').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            const nimi = button.getAttribute('data-nimi');
            const kapasiteetti = button.getAttribute('data-kapasiteetti');

            document.getElementById('roomId').value = id;
            document.getElementById('nimi').value = nimi;
            document.getElementById('kapasiteetti').value = kapasiteetti;
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>