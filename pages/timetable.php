<?php
require_once '../includes/header.php';
restrictAccess(['student', 'teacher', 'admin']);

$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($today)));
$week_end = date('Y-m-d', strtotime('friday this week', strtotime($today)));

try {
    if (isStudent()) {
        $stmt = $conn->prepare("
            SELECT a.*, k.nimi AS course_name, o.etunimi AS teacher_firstname, o.sukunimi AS teacher_lastname, t.nimi AS room_name
            FROM aikataulut a
            JOIN kurssit k ON a.kurssi_id = k.tunnus
            JOIN opettajat o ON k.opettaja_id = o.tunnus
            JOIN tilat t ON a.tila_id = t.tunnus
            JOIN kirjautumiset ki ON k.tunnus = ki.kurssi_id
            WHERE ki.opiskelija_id = ? AND DATE(a.alkuaika) BETWEEN ? AND ?
            ORDER BY a.alkuaika
        ");
        $stmt->execute([$_SESSION['student_id'], $week_start, $week_end]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isTeacher()) {
        $stmt = $conn->prepare("
            SELECT a.*, k.nimi AS course_name, t.nimi AS room_name
            FROM aikataulut a
            JOIN kurssit k ON a.kurssi_id = k.tunnus
            JOIN tilat t ON a.tila_id = t.tunnus
            WHERE k.opettaja_id = ? AND DATE(a.alkuaika) BETWEEN ? AND ?
            ORDER BY a.alkuaika
        ");
        $stmt->execute([$_SESSION['teacher_id'], $week_start, $week_end]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (isAdmin()) {
        $stmt = $conn->query("
            SELECT a.*, k.nimi AS course_name, o.etunimi AS teacher_firstname, o.sukunimi AS teacher_lastname, t.nimi AS room_name
            FROM aikataulut a
            JOIN kurssit k ON a.kurssi_id = k.tunnus
            JOIN opettajat o ON k.opettaja_id = o.tunnus
            JOIN tilat t ON a.tila_id = t.tunnus
            WHERE DATE(a.alkuaika) BETWEEN '" . $week_start . "' AND '" . $week_end . "'
            ORDER BY a.alkuaika
        ");
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo "Query failed: " . $e->getMessage();
    exit();
}

// Group schedules by day and time slot
$timetable = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$times = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00'];

foreach ($schedules as $schedule) {
    $day = date('l', strtotime($schedule['alkuaika']));
    $time_slot = date('H:00', strtotime($schedule['alkuaika']));
    if (!isset($timetable[$day][$time_slot])) {
        $timetable[$day][$time_slot] = [];
    }
    $timetable[$day][$time_slot][] = $schedule;
}
?>

<h2>Timetable for Week <?php echo date('W', strtotime($today)); ?> (<?php echo $week_start . ' - ' . $week_end; ?>)</h2>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Time</th>
            <?php foreach ($days as $day): ?>
                <th><?php echo $day; ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($times as $time): ?>
            <tr>
                <td><?php echo $time; ?></td>
                <?php foreach ($days as $day): ?>
                    <td>
                        <?php if (isset($timetable[$day][$time])): ?>
                            <?php foreach ($timetable[$day][$time] as $schedule): ?>
                                <div class="schedule-item">
                                    <strong><?php echo htmlspecialchars($schedule['course_name']); ?></strong><br>
                                    <?php if (isset($schedule['teacher_firstname'])): ?>
                                        Teacher: <?php echo htmlspecialchars($schedule['teacher_firstname'] . ' ' . $schedule['teacher_lastname']); ?><br>
                                    <?php endif; ?>
                                    Room: <?php echo htmlspecialchars($schedule['room_name']); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">No lesson</span>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>