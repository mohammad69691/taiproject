<?php
require_once '../includes/header.php';
restrictAccess(['admin', 'teacher', 'student']);

if (isAdmin()) {
    // Fetch all courses for admin
    $stmt = $conn->query("
        SELECT k.*, o.etunimi AS teacher_firstname, o.sukunimi AS teacher_lastname, t.nimi AS room_name, 
               (SELECT COUNT(*) FROM kirjautumiset ki WHERE ki.kurssi_id = k.tunnus) AS participant_count,
               t.kapasiteetti
        FROM kurssit k
        JOIN opettajat o ON k.opettaja_id = o.tunnus
        JOIN tilat t ON k.tila_id = t.tunnus
    ");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (isTeacher()) {
    // Fetch only the teacher's courses
    $stmt = $conn->prepare("
        SELECT k.*, o.etunimi AS teacher_firstname, o.sukunimi AS teacher_lastname, t.nimi AS room_name, 
               (SELECT COUNT(*) FROM kirjautumiset ki WHERE ki.kurssi_id = k.tunnus) AS participant_count,
               t.kapasiteetti
        FROM kurssit k
        JOIN opettajat o ON k.opettaja_id = o.tunnus
        JOIN tilat t ON k.tila_id = t.tunnus
        WHERE k.opettaja_id = ?
    ");
    $stmt->execute([$_SESSION['teacher_id']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (isStudent()) {
    // Fetch all courses for student (read-only)
    $stmt = $conn->query("
        SELECT k.*, o.etunimi AS teacher_firstname, o.sukunimi AS teacher_lastname, t.nimi AS room_name, 
               (SELECT COUNT(*) FROM kirjautumiset ki WHERE ki.kurssi_id = k.tunnus) AS participant_count,
               t.kapasiteetti
        FROM kurssit k
        JOIN opettajat o ON k.opettaja_id = o.tunnus
        JOIN tilat t ON k.tila_id = t.tunnus
    ");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission for admin only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $id = $_POST['id'] ?? null;
    $nimi = $_POST['nimi'];
    $kuvaus = $_POST['kuvaus'];
    $alkupvm = $_POST['alkupvm'];
    $loppupvm = $_POST['loppupvm'];
    $opettaja_id = $_POST['opettaja_id'];
    $tila_id = $_POST['tila_id'];

    try {
        if ($id) {
            $stmt = $conn->prepare("UPDATE kurssit SET nimi = ?, kuvaus = ?, alkupvm = ?, loppupvm = ?, opettaja_id = ?, tila_id = ? WHERE tunnus = ?");
            $stmt->execute([$nimi, $kuvaus, $alkupvm, $loppupvm, $opettaja_id, $tila_id, $id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO kurssit (nimi, kuvaus, alkupvm, loppupvm, opettaja_id, tila_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nimi, $kuvaus, $alkupvm, $loppupvm, $opettaja_id, $tila_id]);
        }
        header("Location: courses.php");
        exit();
    } catch (PDOException $e) {
        echo "Operation failed: " . $e->getMessage();
        exit();
    }
}

// Delete course (admin only)
if (isset($_GET['delete']) && isAdmin()) {
    $id = $_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM kurssit WHERE tunnus = ?");
        $stmt->execute([$id]);
        header("Location: courses.php");
        exit();
    } catch (PDOException $e) {
        echo "Delete failed: " . $e->getMessage();
        exit();
    }
}
?>
<h2>Courses</h2>
<?php if (isAdmin()): ?>
    <!-- Add/Edit Modal -->
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#courseModal">Add Course</button>
    <div class="modal fade" id="courseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add/Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="courseId">
                        <div class="mb-3">
                            <label for="nimi" class="form-label">Course Name</label>
                            <input type="text" class="form-control" id="nimi" name="nimi" required>
                        </div>
                        <div class="mb-3">
                            <label for="kuvaus" class="form-label">Description</label>
                            <textarea class="form-control" id="kuvaus" name="kuvaus"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="alkupvm" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="alkupvm" name="alkupvm" required>
                        </div>
                        <div class="mb-3">
                            <label for="loppupvm" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="loppupvm" name="loppupvm" required>
                        </div>
                        <div class="mb-3">
                            <label for="opettaja_id" class="form-label">Teacher</label>
                            <select class="form-control" id="opettaja_id" name="opettaja_id" required>
                                <?php
                                $teachers = $conn->query("SELECT * FROM opettajat")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['tunnus']; ?>">
                                        <?php echo htmlspecialchars($teacher['etunimi'] . ' ' . $teacher['sukunimi']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="tila_id" class="form-label">Room</label>
                            <select class="form-control" id="tila_id" name="tila_id" required>
                                <?php
                                $rooms = $conn->query("SELECT * FROM tilat")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['tunnus']; ?>">
                                        <?php echo htmlspecialchars($room['nimi']); ?>
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
<?php endif; ?>

<!-- Course List -->
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Teacher</th>
            <th>Room</th>
            <th>Participants</th>
            <?php if (isAdmin()): ?>
                <th>Actions</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($courses as $course): ?>
            <tr>
                <td><?php echo htmlspecialchars($course['nimi']); ?></td>
                <td><?php echo htmlspecialchars($course['kuvaus'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($course['alkupvm']); ?></td>
                <td><?php echo htmlspecialchars($course['loppupvm']); ?></td>
                <td><?php echo htmlspecialchars($course['teacher_firstname'] . ' ' . $course['teacher_lastname']); ?></td>
                <td><?php echo htmlspecialchars($course['room_name']); ?></td>
                <td>
                    <?php
                    if (isset($course['participant_count'])) {
                        echo $course['participant_count'];
                        if ($course['participant_count'] > $course['kapasiteetti']) {
                            echo ' <span class="warning-icon">⚠️</span>';
                        }
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td>
                    <?php if (isAdmin()): ?>
                        <button class="btn btn-sm btn-warning edit-course" data-id="<?php echo $course['tunnus']; ?>" data-nimi="<?php echo $course['nimi']; ?>" data-kuvaus="<?php echo $course['kuvaus']; ?>" data-alkupvm="<?php echo $course['alkupvm']; ?>" data-loppupvm="<?php echo $course['loppupvm']; ?>" data-opettaja_id="<?php echo $course['opettaja_id']; ?>" data-tila_id="<?php echo $course['tila_id']; ?>" data-bs-toggle="modal" data-bs-target="#courseModal">Edit</button>
                        <a href="?delete=<?php echo $course['tunnus']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.edit-course').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            const nimi = button.getAttribute('data-nimi');
            const kuvaus = button.getAttribute('data-kuvaus');
            const alkupvm = button.getAttribute('data-alkupvm');
            const loppupvm = button.getAttribute('data-loppupvm');
            const opettaja_id = button.getAttribute('data-opettaja_id');
            const tila_id = button.getAttribute('data-tila_id');

            document.getElementById('courseId').value = id;
            document.getElementById('nimi').value = nimi;
            document.getElementById('kuvaus').value = kuvaus;
            document.getElementById('alkupvm').value = alkupvm;
            document.getElementById('loppupvm').value = loppupvm;
            document.getElementById('opettaja_id').value = opettaja_id;
            document.getElementById('tila_id').value = tila_id;
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>