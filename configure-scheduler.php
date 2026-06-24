<?php

$os = PHP_OS_FAMILY;
$projectRoot = __DIR__;
$artisanPath = $projectRoot . DIRECTORY_SEPARATOR . 'artisan';
$phpBinary = PHP_BINARY;

echo "Detecting OS: $os\n";
echo "Project Root: $projectRoot\n";

if ($os === 'Linux' || $os === 'Darwin') {
    setupLinuxCron($projectRoot, $phpBinary);
} elseif ($os === 'Windows') {
    setupWindowsTask($artisanPath, $phpBinary);
} else {
    echo "Unsupported OS: $os\n";
}

function setupLinuxCron($projectRoot, $phpBinary) {
    $cronJob = "* * * * * cd $projectRoot && $phpBinary artisan schedule:run >> /dev/null 2>&1";
    
    // Get current crontab
    $currentCron = shell_exec('crontab -l 2>/dev/null') ?? '';
    
    if (strpos($currentCron, $projectRoot . ' && ' . $phpBinary . ' artisan schedule:run') !== false) {
        echo "Cron job already exists for this project.\n";
        return;
    }
    
    $newCron = $currentCron . ($currentCron ? "\n" : "") . $cronJob . "\n";
    
    // Write new crontab
    $tmpFile = tempnam(sys_get_temp_dir(), 'cron');
    file_put_contents($tmpFile, $newCron);
    
    exec("crontab $tmpFile", $output, $resultCode);
    unlink($tmpFile);
    
    if ($resultCode === 0) {
        echo "Cron job successfully configured.\n";
    } else {
        echo "Failed to configure cron job. Make sure you have permissions to run 'crontab'.\n";
    }
}

function setupWindowsTask($artisanPath, $phpBinary) {
    $taskName = "VillaDonqV2_Laravel_Scheduler";
    $command = "\"$phpBinary\" \"$artisanPath\" schedule:run";
    
    // Create the scheduled task
    // /sc minute /mo 1 means every 1 minute
    // /f means force overwrite if exists
    $schtasksCommand = "schtasks /create /tn \"$taskName\" /tr \"$command\" /sc minute /mo 1 /f";
    
    echo "Executing command: $schtasksCommand\n";
    exec($schtasksCommand, $output, $resultCode);
    
    if ($resultCode === 0) {
        echo "Windows Scheduled Task '$taskName' successfully created.\n";
    } else {
        echo "Failed to create Windows Scheduled Task. Ensure you are running this script as Administrator.\n";
        echo "Output: " . implode("\n", $output) . "\n";
    }
}
