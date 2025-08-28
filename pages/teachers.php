<?php
require_once '../includes/header.php';
restrictAccess(['admin']);

$stmt = $conn->query("SELECT * FROM opettajat");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $etunimi = $_POST['etunimi'];
    $sukunimi = $_POST['sukunimi'];
    $aine = $_POST['aine'];

    if ($id) {
        $stmt = $conn->prepare("UPDATE opettajat SET etunimi = ?, sukunimi = ?, aine = ? WHERE tunnus = ?");
        $stmt->execute([$etunimi, $sukunimi, $aine, $id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO opettajat (etunimi, sukunimi, aine) VALUES (?, ?, ?)");
        $stmt->execute([$etunimi, $sukunimi, $aine]);
    }
    header("Location: teachers.php");
    exit();
}

// Delete teacher
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM opettajat WHERE tunnus = ?");
    $stmt->execute([$id]);
    header("Location: teachers.php");
    exit();
}
?>
<h2>Teachers</h2>
<!-- Add/Edit Modal -->
<button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#teacherModal">Add Teacher</button>
<div class="modal fade" id="teacherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add/Edit Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id" id="teacherId">
                    <div class="mb-3">
                        <label for="etunimi" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="etunimi" name="etunimi" required>
                    </div>
                    <div class="mb-3">
                        <label for="sukunimi" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="sukunimi" name="sukunimi" required>
                    </div>
                    <div class="mb-3">
                        <label for="aine" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="aine" name="aine" required>
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

<!-- Teacher List -->
<table class="table table-bordered">
    <thead>
        <tr>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Subject</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($teachers as $teacher): ?>
            <tr>
                <td><?php echo htmlspecialchars($teacher['etunimi']); ?></td>
                <td><?php echo htmlspecialchars($teacher['sukunimi']); ?></td>
                <td><?php echo htmlspecialchars($teacher['aine']); ?></td>
                <td>
                    <button class="btn btn-sm btn-warning edit-teacher" data-id="<?php echo $teacher['tunnus']; ?>" data-etunimi="<?php echo $teacher['etunimi']; ?>" data-sukunimi="<?php echo $teacher['sukunimi']; ?>" data-aine="<?php echo $teacher['aine']; ?>" data-bs-toggle="modal" data-bs-target="#teacherModal">Edit</button>
                    <a href="?delete=<?php echo $teacher['tunnus']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.edit-teacher').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            const etunimi = button.getAttribute('data-etunimi');
            const sukunimi = button.getAttribute('data-sukunimi');
            const aine = button.getAttribute('data-aine');

            document.getElementById('teacherId').value = id;
            document.getElementById('etunimi').value = etunimi;
            document.getElementById('sukunimi').value = sukunimi;
            document.getElementById('aine').value = aine;
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>