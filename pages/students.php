<?php
require_once '../includes/header.php';
restrictAccess(['admin', 'student']);

if (isAdmin()) {
    // Fetch all students
    $stmt = $conn->query("SELECT * FROM opiskelijat");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (isStudent()) {
    // Fetch only the logged-in student's info
    $stmt = $conn->prepare("SELECT * FROM opiskelijat WHERE opiskelijanumero = ?");
    $stmt->execute([$_SESSION['student_id']]);
    $students = [$stmt->fetch(PDO::FETCH_ASSOC)];
}

// Handle form submission for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $id = $_POST['id'] ?? null;
    $etunimi = $_POST['etunimi'];
    $sukunimi = $_POST['sukunimi'];
    $syntyma = $_POST['syntyma'];
    $vuosikurssi = $_POST['vuosikurssi'];

    if ($id) {
        $stmt = $conn->prepare("UPDATE opiskelijat SET etunimi = ?, sukunimi = ?, syntyma = ?, vuosikurssi = ? WHERE opiskelijanumero = ?");
        $stmt->execute([$etunimi, $sukunimi, $syntyma, $vuosikurssi, $id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO opiskelijat (etunimi, sukunimi, syntyma, vuosikurssi) VALUES (?, ?, ?, ?)");
        $stmt->execute([$etunimi, $sukunimi, $syntyma, $vuosikurssi]);
    }
    header("Location: students.php");
    exit();
}

// Delete student
if (isset($_GET['delete']) && isAdmin()) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM opiskelijat WHERE opiskelijanumero = ?");
    $stmt->execute([$id]);
    header("Location: students.php");
    exit();
}
?>
<h2>Students</h2>
<?php if (isAdmin()): ?>
    <!-- Add/Edit Modal -->
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#studentModal">Add Student</button>
    <div class="modal fade" id="studentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add/Edit Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="studentId">
                        <div class="mb-3">
                            <label for="etunimi" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="etunimi" name="etunimi" required>
                        </div>
                        <div class="mb-3">
                            <label for="sukunimi" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="sukunimi" name="sukunimi" required>
                        </div>
                        <div class="mb-3">
                            <label for="syntyma" class="form-label">Birth Date</label>
                            <input type="date" class="form-control" id="syntyma" name="syntyma" required>
                        </div>
                        <div class="mb-3">
                            <label for="vuosikurssi" class="form-label">Year</label>
                            <input type="number" class="form-control" id="vuosikurssi" name="vuosikurssi" required>
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
<?php endif; ?>

<!-- Student List -->
<table class="table table-bordered">
    <thead>
        <tr>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Birth Date</th>
            <th>Year</th>
            <?php if (isAdmin()): ?>
                <th>Actions</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($students as $student): ?>
            <tr>
                <td><?php echo htmlspecialchars($student['etunimi']); ?></td>
                <td><?php echo htmlspecialchars($student['sukunimi']); ?></td>
                <td><?php echo htmlspecialchars($student['syntyma']); ?></td>
                <td><?php echo htmlspecialchars($student['vuosikurssi']); ?></td>
                <?php if (isAdmin()): ?>
                    <td>
                        <button class="btn btn-sm btn-warning edit-student" data-id="<?php echo $student['opiskelijanumero']; ?>" data-etunimi="<?php echo $student['etunimi']; ?>" data-sukunimi="<?php echo $student['sukunimi']; ?>" data-syntyma="<?php echo $student['syntyma']; ?>" data-vuosikurssi="<?php echo $student['vuosikurssi']; ?>" data-bs-toggle="modal" data-bs-target="#studentModal">Edit</button>
                        <a href="?delete=<?php echo $student['opiskelijanumero']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Student Courses (for students) -->
<?php if (isStudent()): ?>
    <h3>My Courses</h3>
    <?php
    $stmt = $conn->prepare("SELECT k.nimi, k.alkupvm FROM kurssit k JOIN kirjautumiset ki ON k.tunnus = ki.kurssi_id WHERE ki.opiskelija_id = ?");
    $stmt->execute([$_SESSION['student_id']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Course Name</th>
                <th>Start Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($courses as $course): ?>
                <tr>
                    <td><?php echo htmlspecialchars($course['nimi']); ?></td>
                    <td><?php echo htmlspecialchars($course['alkupvm']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.edit-student').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            const etunimi = button.getAttribute('data-etunimi');
            const sukunimi = button.getAttribute('data-sukunimi');
            const syntyma = button.getAttribute('data-syntyma');
            const vuosikurssi = button.getAttribute('data-vuosikurssi');

            document.getElementById('studentId').value = id;
            document.getElementById('etunimi').value = etunimi;
            document.getElementById('sukunimi').value = sukunimi;
            document.getElementById('syntyma').value = syntyma;
            document.getElementById('vuosikurssi').value = vuosikurssi;
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>