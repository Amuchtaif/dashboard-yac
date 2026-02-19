<?php
namespace App\Payroll\Repositories;

use PDO;

class AttendanceRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getEmployeeWithPosition($user_id)
    {
        $query = "SELECT e.nik, e.full_name, e.status, p.name as position_name 
                  FROM employees e 
                  LEFT JOIN positions p ON e.position_id = p.id 
                  WHERE e.id = :id 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $user_id]);
        return $stmt->fetch();
    }

    public function getEmployeeWithPositionByNik($nik)
    {
        $query = "SELECT e.nik, e.full_name, e.status, p.name as position_name 
                  FROM employees e 
                  LEFT JOIN positions p ON e.position_id = p.id 
                  WHERE e.nik = :nik 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['nik' => $nik]);
        return $stmt->fetch();
    }
}
