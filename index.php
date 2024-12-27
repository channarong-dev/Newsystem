<?php
// ฟังก์ชันสำหรับอ่านข้อมูลจากไฟล์ JSON
function readData() {
    $file = 'data.json';
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([]));
    }
    return json_decode(file_get_contents($file), true);
}

// ฟังก์ชันสำหรับเขียนข้อมูลลงไฟล์ JSON
function writeData($data) {
    file_put_contents('data.json', json_encode($data, JSON_PRETTY_PRINT));
}

// ฟังก์ชันสำหรับค้นหาข้อมูลตามเดือน
function filterDataByMonth($data, $month, $year) {
    return array_filter($data, function ($item) use ($month, $year) {
        $itemDate = strtotime($item['date']);
        return date('m', $itemDate) == $month && date('Y', $itemDate) == $year;
    });
}

// ฟังก์ชันสรุปค่าใช้จ่ายและรายรับ
function summarizeData($data) {
    $summary = ['income' => 0, 'expense' => 0, 'balance' => 0];
    foreach ($data as $item) {
        if ($item['type'] === 'income') {
            $summary['income'] += $item['amount'];
        } elseif ($item['type'] === 'expense') {
            $summary['expense'] += $item['amount'];
        }
    }
    $summary['balance'] = $summary['income'] - $summary['expense'];
    return $summary;
}

// จัดการการบันทึก / ลบ / อัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = readData();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // เพิ่มรายการใหม่
        $data[] = [
            'type' => $_POST['type'],
            'name' => $_POST['name'],
            'amount' => number_format((float)$_POST['amount'], 2, '.', ''),
            'date' => $_POST['date'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    } elseif ($action === 'delete') {
        // ลบรายการ
        $index = $_POST['index'];
        array_splice($data, $index, 1);
    } elseif ($action === 'edit') {
        // แก้ไขรายการ
        $index = $_POST['index'];
        $data[$index] = [
            'type' => $_POST['type'],
            'name' => $_POST['name'],
            'amount' => number_format((float)$_POST['amount'], 2, '.', ''),
            'date' => $_POST['date'],
            'created_at' => $data[$index]['created_at'], // คงเวลาที่สร้างเดิมไว้
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    writeData($data);
    header('Location: index.php');
    exit();
}

$data = readData();
$filteredData = $data;
$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['month'], $_GET['year'])) {
    $month = $_GET['month'];
    $year = $_GET['year'];
    $filteredData = filterDataByMonth($data, $month, $year);
    $summary = summarizeData($filteredData);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบันทึกรายรับรายจ่าย</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>ระบบบันทึกรายรับรายจ่าย</h1>

    <!-- ฟอร์มเพิ่ม/แก้ไขรายการ -->
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="index" id="edit-index" value="">
        <label>ประเภท:</label>
        <select name="type" id="edit-type" required>
            <option value="income">รายรับ</option>
            <option value="expense">รายจ่าย</option>
        </select>
        <label>ชื่อรายการ:</label>
        <input type="text" name="name" id="edit-name" required>
        <label>จำนวนเงิน:</label>
        <input type="number" name="amount" id="edit-amount" step="0.01" required>
        <label>วันที่:</label>
        <input type="date" name="date" id="edit-date" required>
        <button type="submit">บันทึก</button>
    </form>

    <!-- ฟอร์มค้นหารายการตามเดือน -->
    <form method="GET">
        <label>เดือน:</label>
        <select name="month" required>
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>"
                    <?= isset($_GET['month']) && $_GET['month'] == $i ? 'selected' : '' ?>>
                    <?= date('F', mktime(0, 0, 0, $i, 10)) ?>
                </option>
            <?php endfor; ?>
        </select>
        <label>ปี:</label>
        <input type="number" name="year" value="<?= $_GET['year'] ?? date('Y') ?>" required>
        <button type="submit">ค้นหา</button>
    </form>

    <!-- แสดงรายการที่บันทึก -->
    <h2>รายการทั้งหมด</h2>
    <table>
        <thead>
            <tr>
                <th>ประเภท</th>
                <th>ชื่อรายการ</th>
                <th>จำนวนเงิน</th>
                <th>วันที่</th>
                <th>เวลาบันทึก</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filteredData as $index => $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['type']) ?></td>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= htmlspecialchars($item['amount']) ?></td>
                    <td><?= htmlspecialchars($item['date']) ?></td>
                    <td><?= htmlspecialchars($item['created_at']) ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="index" value="<?= $index ?>">
                            <button type="submit" onclick="return confirm('คุณต้องการลบรายการนี้หรือไม่?')">ลบ</button>
                        </form>
                        <button onclick="editItem(<?= $index ?>, '<?= $item['type'] ?>', '<?= htmlspecialchars($item['name']) ?>', <?= $item['amount'] ?>, '<?= $item['date'] ?>')">แก้ไข</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- แสดงรายงานสรุป -->
    <?php if ($summary): ?>
        <h2>รายงานสรุป</h2>
        <p>รายรับทั้งหมด: <?= number_format($summary['income'], 2) ?></p>
        <p>รายจ่ายทั้งหมด: <?= number_format($summary['expense'], 2) ?></p>
        <p>ยอดคงเหลือ: <?= number_format($summary['balance'], 2) ?></p>
    <?php endif; ?>

    <script>
        function editItem(index, type, name, amount, date) {
            document.querySelector('form input[name=action]').value = 'edit';
            document.querySelector('#edit-index').value = index;
            document.querySelector('#edit-type').value = type;
            document.querySelector('#edit-name').value = name;
            document.querySelector('#edit-amount').value = amount;
            document.querySelector('#edit-date').value = date;
        }
    </script>
</body>
</html>
