<?php
/** Exercise UNA's native ZipArchive::getNameIndex(0) package-root contract. */
$failed = 0;
foreach (array_slice($argv, 1) as $path) {
    $name = basename($path);
    if (preg_match('/^my-una-doctor-(0\.6\.[1-7])-to-0\.6\.8\.zip$/', $name, $match))
        $expected = 'update_' . $match[1] . '_0.6.8/';
    elseif ($name === 'my-una-doctor-0.6.8.zip')
        $expected = 'my_una_doctor/';
    else
        throw new RuntimeException('Unexpected archive: ' . $name);
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Cannot open ' . $path);
    $actual = $zip->getNameIndex(0);
    $zip->getExternalAttributesIndex(0, $system, $attributes);
    $ok = $actual === $expected && $system === ZipArchive::OPSYS_UNIX
        && (($attributes >> 16) & 0177777) === 040755;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ' native first entry: ' . $actual . PHP_EOL;
    if (!$ok) ++$failed;
    $zip->close();
}
exit($failed ? 1 : 0);
