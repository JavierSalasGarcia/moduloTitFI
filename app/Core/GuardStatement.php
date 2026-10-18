<?php
declare(strict_types=1);
namespace App\Core;
final class GuardStatement extends \PDOStatement
{
    protected function __construct() {}
    public function execute(?array $params=null): bool
    {
        AccessControl::assertSql($this->queryString);
        $ok=parent::execute($params);
        if ($ok) Audit::sql($this->queryString,$this->rowCount());
        return $ok;
    }
}
