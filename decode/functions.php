<?php

if (!\function_exists('create_mail_pipe_db')) {
    /**
     * Create database if doesn't exists
     *
     * @param object $dbc
     * @return bool
     * @throws \Exception if creation failure
     */
	function create_mail_pipe_db($dbc = null) 
	{
		if ($dbc instanceof \PDO) {
            $rows = $dbc->query("SHOW TABLES LIKE 'emails'")->rowCount();
			if ($rows<1) {
				$rows = $dbc->exec("CREATE TABLE emails (
                    id int(255) NOT NULL AUTO_INCREMENT,
                    user varchar(255) NOT NULL,
                    toaddr varchar(255) NOT NULL,
                    sender varchar(255) NOT NULL,
                    fromaddr varchar(255) NOT NULL,
                    date varchar(255) NOT NULL,
                    subject varchar(255) NOT NULL,
                    plain text NOT NULL,
                    html text NOT NULL,
                    maildate timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id)
                  ) AUTO_INCREMENT=2;");

                unset($rows);
                $rows = $dbc->query("SHOW TABLES LIKE 'emails'")->rowCount();
                if (!$rows)
                    throw new \Exception("Failed to create emails database table.".\PHP_EOL.$dbc->errorInfo()[2]);
            }

			$rows = $dbc->query("SHOW TABLES LIKE 'files'")->rowCount();
			if ($rows<1) {
				$rows = $dbc->exec("CREATE TABLE files (
					id int(255) NOT NULL AUTO_INCREMENT,
					email int(255) NOT NULL,
					name varchar(255) NOT NULL,
					path varchar(510) NOT NULL,
					size varchar(20) NOT NULL,
					mime varchar(100) NOT NULL,
					PRIMARY KEY (id)
                    ) AUTO_INCREMENT=1;");

                unset($rows);
			    $rows = $dbc->query("SHOW TABLES LIKE 'files'")->rowCount();
                if (!$rows)
                    throw new \Exception("Failed to create files database table.".\PHP_EOL.$dbc->errorInfo()[2]);
            }

		} else {
            return false;
		}
		
		return true;
    }
    
}