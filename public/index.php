<?php
class analyzer {
    private $records;

    public function __construct($jsonText){
        $data = json_decode($jsonText, true);

        if ($data === null) {
            throw new Exception("Invalid JSON file.");
        }

        if (is_array($data)) {
            $this->records = $data;
        } else {
            $this->records = $data['features'] ?? $data['records'] ?? [];
        }

        if (empty($this->records)) {
            throw new Exception("The dataset is empty.");
        }
    }

    public function getExtremes($freqMap, $findMax = true, $defaultVal = "N/A") {
        $targetKey = $defaultVal;
        $targetVal;
        
        if ($findMax) {
            $targetVal = -1;
        } else {
            $targetVal = INF;
        }

        foreach ($freqMap as $key => $count) {
            if ($findMax && $count > $targetVal) {
                    $targetVal = $count;
                    $targetKey = $key; 
            } elseif (!$findMax && $count < $targetVal) {
                $targetVal = $count;
                $targetKey = $key;
            }
        }
        return $targetKey;
    }

    public function stats() {
        $validRecords = array_filter($this->records, function($record) {
            return $record !== null;
        });

        $mappedRecords = [];
        array_map(function($record) use (&$mappedRecords) {
            $rec = $record['properties'] ?? $record;
            array_push($mappedRecords, [
                'psa' => $rec['PSA'] ?? $rec['police_service_area'] ?? "Unknown",
                'offense' => $rec['OFFENSE'] ?? $rec['offense'] ?? "Unknown",
                'method' => $rec['METHOD'] ?? $rec['method'] ?? "None",
                'shift' => $rec['SHIFT'] ?? $rec['shift'] ?? "Unknown",
                'date' => $rec['REPORT_DATE'] ?? $rec['REPORT_DAT'] ?? $rec['report_date'] ?? ""
            ]);
        }, $validRecords);

        $psaMap = [];
        $offenseMap = [];
        $methodMap = [];
        $shiftMap = [];
        $monthMap = [];
        $monthNames = ["January", "February", "March", "April", "May", "June", 
            "July", "August", "September", "October", "November", "December"];

        for ($i = 0; $i <count($mappedRecords); $i++) {
            $rec = $mappedRecords[$i];
            $psaMap[$rec['psa']] = ($psaMap[$rec['psa']] ?? 0) + 1;
            $offenseMap[$rec['offense']] = ($offenseMap[$rec['offense']] ?? 0) + 1;
            $methodMap[$rec['method']] = ($methodMap[$rec['method']] ?? 0) + 1;
            $shiftMap[$rec['shift']] = ($shiftMap[$rec['shift']] ?? 0) + 1;
            
            $monthName = "Unknown";
            if (!empty($rec['date'])) {
                $timestamp = strtotime($rec['date']);
                if ($timestamp !== false) {
                    $monthName = $monthNames[(int)date('n', $timestamp) - 1];
                }
            }
            $monthMap[$monthName] = ($monthMap[$monthName] ?? 0) + 1;
        }

        $janTotal = $monthMap['January'] ?? 0;
        $janDailyAvg = round($janTotal/31, 2);
        
        return [
            'q1' => $janDailyAvg,
            'q2' => $this->getExtremes($psaMap, true),
            'q3' => $this->getExtremes($offenseMap, true),
            'q4' => $this->getExtremes($methodMap, true),
            'q5' => $this->getExtremes($psaMap, false),
            'q6' => $this->getExtremes($offenseMap, false),
            'q7' => $this->getExtremes($methodMap, false),
            'q8' => $this->getExtremes($shiftMap, true),
            'q9' => $this->getExtremes($shiftMap, false),
            'q10' => $this->getExtremes($monthMap, true),
        ];
    }
}

$startTime = microtime(true);
$timestamp = date("Y-m-d H:i:s");
$errorMessage = "";
$stats = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['jsonFile']) || $_FILES['jsonFile']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please upload a valid JSON file.");
        }

        $fileContent = file_get_contents($_FILES['jsonFile']['tmp_name']);
        if ($fileContent === false) {
            throw new Exception("Failed to read the uploaded file.");
        }

        $analyzerObj = new analyzer($fileContent);
        $stats = $analyzerObj->stats();

    } catch (Exception $e) {
        $errorMessage = htmlspecialchars($e->getMessage());
    }
}

$endTime = microtime(true);
$processingTime = round(($endTime - $startTime) * 1000, 2);
?>

<!DOCTYPE html>
<html lang="">
    <head>
        <meta charset ="utf-8">
        <title>Homework3</title>
        <link rel="stylesheet" href="HW3.css">
    </head>
    <body>
        <header></header>
        <main>
        <div>
        <ol>
            <li id="q1">What was the averge number of reported crime incidents per day in January?<strong><?php echo isset($stats) ? htmlspecialchars($stats['q1']) : ''; ?></strong></li>
            <li id="q2">What police service area reported the most crimes?<strong><?php echo isset($stats) ? htmlspecialchars($stats['q2']) : ''; ?></strong></li>
            <li id="q3">What was the most common offense?<strong><?php echo isset($stats) ? htmlspecialchars($stats['q3']) : ''; ?></strong></li>
            <li id="q4">What was the most common method?<strong><?php echo isset($stats) ? htmlspecialchars($stats['q4']) : ''; ?></strong></li>
            <li id="q5">What police service area reported the least crimes?<strong><?php echo isset($stats) ? htmlspecialchars($stats['q5']) : ''; ?></strong></li>
            <li id="q6">What was the least common offense?<strong><?php echo isset($stats) ? htmlspecialchars($stats['q6']) : ''; ?></strong></li>
            <li id="q7">What was the least common method?<strong><?php echo isset($stats) ? htmlspecialchars($stats['q7']) : ''; ?></strong></li>
            <li id="q8">Which shift received the most amount of criminal activity?<strong><?php echo isset($stats) ? htmlspecialchars($stats['q8']) : ''; ?></strong></li>
            <li id="q9">Which shift received the least amount of criminal activity?<strong><?php echo isset($stats) ? htmlspecialchars($stats['q9']) : ''; ?></strong></li>
            <li id="q10">Which month had the most crimes reported?<strong><?php echo isset($stats) ? htmlspecialchars($stats['q10']) : ''; ?></strong></li>
        </ol>
        </div>

        <form action="index.php" method="POST" enctype="multipart/form-data">
            <input type="file" id="picker" name="jsonFile" accept=".geojson , .json"/>
            <button type="submit">Submit</button>
        </form>
        
        <?php if (!empty($errorMessage)): ?>
            <div>
                Error Processing File: <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>

        <div id="processing-info">
            <?php if (isset($stats)): ?>
               <?php echo "Processed on: {$timestamp} | Total processing time: {$processingTime} ms"; ?>
            <?php endif; ?>
        </div>
        </main>
        <footer></footer>
    </body>
</html>