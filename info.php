<?php

echo extension_loaded('pdo_pgsql') ? 'PDO_PGSQL AKTIF' : 'PDO_PGSQL TIDAK AKTIF';
echo "<br>";
echo extension_loaded('pgsql') ? 'PGSQL AKTIF' : 'PGSQL TIDAK AKTIF';