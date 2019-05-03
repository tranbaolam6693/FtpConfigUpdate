# FtpConfigUpdate
Bỏ FtpConfig.php => /library/Custom/Controller/Batch/

Bỏ FtpConfigUpdate.php + FtpConfigRollback.php => /application/batch/controllers/


_> php index.php development app FtpConfigUpdate {key} {old_value] {new_value} {date} {log_file_name}_

_> php index.php development app FtpConfigRollback {version} {date} {log_file_name}_