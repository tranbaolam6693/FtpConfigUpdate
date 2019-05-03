# FtpConfigUpdate

**Install**
Bỏ FtpConfig.php => /library/Custom/Controller/Batch/

Bỏ FtpConfigUpdate.php + FtpConfigRollback.php => /application/batch/controllers/

**Usage:**

_> php index.php development app FtpConfigUpdate {key} {old_value] {new_value} {date} {log_file_name}_

_> php index.php development app FtpConfigRollback {version} {date} {log_file_name}_



**Note::** 
{log_file_name} có thể truyền hoặc không.

_/var/www/html/application/../log/Batch_FtpConfigUpdate_FtpLoginFail_1556855778.log _

_> {log_file_name} = Batch_FtpConfigUpdate_FtpLoginFail_1556855778_

