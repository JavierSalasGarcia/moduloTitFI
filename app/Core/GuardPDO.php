<?php
declare(strict_types=1);
namespace App\Core;
final class GuardPDO extends \PDO
{
    public function exec(string $statement): int|false
    {
        AccessControl::assertSql($statement); $n=parent::exec($statement);
        if ($n!==false) Audit::sql($statement,$n); return $n;
    }
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): \PDOStatement|false
    {
        AccessControl::assertSql($query);
        $s=$fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$fetchModeArgs);
        if ($s) Audit::sql($query,$s->rowCount()); return $s;
    }
}
