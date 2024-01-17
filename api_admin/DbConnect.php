<?php
    class DbConnect {

        // databse credentials 
        
        
        private $server = 'localhost';
        private $dbname = 'u625732261_v1ufd4937A4323';
        private $user = 'u625732261_v1i39c_49d82_3';
        private $pass = 'e8w5yi^QqAZ=';
 
        // function to connect with database
        public function connect() {
            try {
                // trying to make a connection to the database
                $conn = new PDO('mysql:host=' .$this->server .';dbname=' . $this->dbname, $this->user, $this->pass);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                if ($conn) {
                }
                return $conn;
            } catch (\Exception $e) {

                // Notifying if there is an error while connecting to Daabase 
                echo "Database Error: " . $e->getMessage();
            }
        }
         
    }
?>
