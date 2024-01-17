<?php
    class DbConnect {
        private $server = 'localhost';
        private $dbname = 'u625732261_v1ufd4937A4323';
        private $user = 'u625732261_v1i39c_49d82_3';
        private $pass = 'e8w5yi^QqAZ=';
 
        public function connect() {
            try {
                $conn = new PDO('mysql:host=' .$this->server .';dbname=' . $this->dbname, $this->user, $this->pass);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                if ($conn) {
                }
                return $conn;
            } catch (\Exception $e) {
                echo "Database Error: " . $e->getMessage();
            }
        }
         
    }
?>