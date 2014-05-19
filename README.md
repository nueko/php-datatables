EXAMPLE
=======

```php
require 'PATH/TO/Engine/BaseEngine.php';
require 'PATH/TO/Engine/Pdo.php';

try {
    $dbh = new PDO("mysql:host=localhost;dbname=bill", 'user', '123');

    $ins = new \Sum\DataTables\Engine\Pdo($dbh);
        $ins->select(['UNIT' => 'nm_unit', 'DRD'=> 'IFNULL(DRD, 0)', 'LPP' => 'IFNULL(LPP, 0)', 'EFISIENSI' => 'IFNULL(ROUND(LPP / drd * 100, 2), 0)'])
            ->from('munit as U')
            ->rightJoin([
                'TA' => "SELECT LEFT(nosamw, 2) AS unit, SUM( r1 + r2 + r3 + r4 + dnmet + adm + materai + listrik + ret ) AS LPP FROM rekair WHERE TG = 0 AND statrek = 'A' AND periode ='201402'AND (tgl_byr BETWEEN'2014-03-01'AND LAST_DAY('2014-03-01')) GROUP BY LEFT(nosamw, 2)"
            ], 'U.unit = TA.unit')
            ->leftJoin([
                'TB' => "SELECT LEFT(nosamw, 2) AS unit, SUM( r1 + r2 + r3 + r4 + dnmet + adm + materai + listrik + ret ) AS DRD FROM rekair WHERE periode ='201402'AND statrek = 'A' GROUP BY LEFT(nosamw, 2)"
            ], 'U.unit = TB.unit');
        echo $ins->make();

    }
catch(PDOException $e)
{
    echo $e->getMessage();
}
```


Contribution needed!