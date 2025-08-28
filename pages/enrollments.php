<?php
require_once '../includes/header.php';
restrictAccess(['admin', 'teacher']); // Deny students

try {
    if (isAdmin()) {
        $stmt = $conn->query("
            SELECT ki.tunnus, ki.opiskelija_id, ki.kurssi_id, o.etunimi AS student_firstname, 
                   o.sukunimi AS student_lastname, k.nimi AS course_name, ki.kirjautumis_aika
            FROM kirjautumiset ki
            JOIN opiskelijat o ON ki.opiskelija_id = o.opiskelijanumero
            JOIN kurssit k ON ki.kurssi_id = k.tunnus
        ");
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isTeacher()) {
        $stmt = $conn->prepare("
            SELECT ki.tunnus, ki.opiskelija_id, ki.kurssi_id, o.etunimi AS student_firstname, 
                   o.sukunimi AS student_lastname, k.nimi AS course_name, ki.kirjautumis_aika
            FROM kirjautumiset ki
            JOIN opiskelijat o ON ki.opiskelija_id = o.opiskelijanumero
            JOIN kurssit k ON ki.kurssi_id = k.tunnus
            WHERE k.opettaja_id = ?
        ");
        $stmt->execute([$_SESSION['teacher_id']]);
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo "Query failed: " . $e->getMessage();
    exit();
}

// Handle form submission for admin/teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isAdmin() || isTeacher())) {
    $id = $_POST['id'] ?? null;
    $opiskelija_id = $_POST['opiskelija_id'];
    $kurssi_id = $_POST['kurssi_id'];

    try {
        if ($id) {
            $stmt = $conn->prepare("UPDATE kirjautumiset SET opiskelija_id = ?, kurssi_id = ?, kirjautumis_aika = NOW() WHERE tunnus = ?");
            $stmt->execute([$opiskelija_id, $kurssi_id, $id]);
        } else {
            if (isTeacher()) {
                $stmt = $conn->prepare("SELECT tunnus FROM kurssit WHERE tunnus = ? AND opettaja_id = ?");
                $stmt->execute([$kurssi_id, $_SESSION['teacher_id']]);
                if ($stmt->rowCount() == 0) {
                    echo "Error: You can only add students to your own courses.";
                    exit();
                }
            }
            $stmt = $conn->prepare("SELECT tunnus FROM kirjautumiset WHERE opiskelija_id = ? AND kurssi_id = ?");
            $stmt->execute([$opiskelija_id, $kurssi_id]);
            if ($stmt->rowCount() > 0) {
                echo "Error: This student is already enrolled in this course.";
                exit();
            }
            $stmt = $conn->prepare("INSERT INTO kirjautumiset (opiskelija_id, kurssi_id, kirjautumis_aika) VALUES (?, ?, NOW())");
            $stmt->execute([$opiskelija_id, $kurssi_id]);
        }
        header("Location: enrollments.php");
        exit();
    } catch (PDOException $e) {
        echo "Operation failed: " . $e->getMessage();
        exit();
    }
}

// Delete enrollment for admin/teacher
if (isset($_GET['delete']) && (isAdmin() || isTeacher())) {
    $id = $_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM kirjautumiset WHERE tunnus = ?");
        $stmt->execute([$id]);
        header("Location: enrollments.php");
        exit();
    } catch (PDOException $e) {
        echo "Delete failed: " . $e->getMessage();
        exit();
    }
}
?>
<h2>Enrollments</h2>
<?php if (isAdmin() || isTeacher()): ?>
    <!-- Add/Edit Modal -->
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#enrollmentModal">Add Enrollment</button>
    <div class="modal fade" id="enrollmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add/Edit Enrollment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="enrollmentId">
                        <div class="mb-3">
                            <label for="opiskelija_id" class="form-label">Student</label>
                            <select class="form-control" id="opiskelija_id" name="opiskelija_id" required>
                                <?php
                                $students = $conn->query("SELECT * FROM opiskelijat")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($students as $student): ?>
                                    <option value="<?php echo $student['opiskelijanumero']; ?>">
                                        <?php echo htmlspecialchars($student['etunimi'] . ' ' . $student['sukunimi']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="kurssi_id" class="form-label">Course</label>
                            <select class="form-control" id="kurssi_id" name="kurssi_id" required>
                                <?php
                                if (isAdmin()) {
                                    $courses = $conn->query("SELECT * FROM kurssit")->fetchAll(PDO::FETCH_ASSOC);
                                } elseif (isTeacher()) {
                                    $stmt = $conn->prepare("SELECT * FROM kurssit WHERE opettaja_id = ?");
                                    $stmt->execute([$_SESSION['teacher_id']]);
                                    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                }
                                foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['tunnus']; ?>">
                                        <?php echo htmlspecialchars($course['nimi']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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

    <!-- Enrollment List -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Student</th>
                <th>Course</th>
                <th>Enrollment Time</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($enrollments)): ?>
                <?php foreach ($enrollments as $enrollment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($enrollment['student_firstname'] . ' ' . $enrollment['student_lastname']); ?></td>
                        <td><?php echo htmlspecialchars($enrollment['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($enrollment['kirjautumis_aika']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning edit-enrollment" 
                                    data-id="<?php echo $enrollment['tunnus']; ?>" 
                                    data-opiskelija_id="<?php echo $enrollment['opiskelija_id']; ?>" 
                                    data-kurssi_id="<?php echo $enrollment['kurssi_id']; ?>" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#enrollmentModal">Edit</button>
                            <a href="?delete=<?php echo $enrollment['tunnus']; ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">No enrollments found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.edit-enrollment').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            const opiskelija_id = button.getAttribute('data-opiskelija_id');
            const kurssi_id = button.getAttribute('data-kurssi_id');

            document.getElementById('enrollmentId').value = id;
            document.getElementById('opiskelija_id').value = opiskelija_id;
            document.getElementById('kurssi_id').value = kurssi_id;
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>