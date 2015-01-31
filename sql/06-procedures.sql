--
-- Fetch first job
--
DELIMITER //
CREATE PROCEDURE getFirstJob
(idPID INT, OUT pID INT)
BEGIN
DECLARE record_not_found INT DEFAULT 0;
DECLARE vPid INT DEFAULT -1;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET record_not_found = 1;
DECLARE EXIT HANDLER FOR SQLEXCEPTION ROLLBACK;
DECLARE EXIT HANDLER FOR SQLWARNING ROLLBACK;
SET pID = 0;
START TRANSACTION;
SELECT id INTO pID FROM list_job WHERE state=1 ORDER BY id ASC LIMIT 0,1;
IF record_not_found THEN
  SET pID = 0;
ELSE
  UPDATE list_job SET state=2, fk_pid = idPid WHERE id = pID;
END IF;
COMMIT;
END //
DELIMITER ;

