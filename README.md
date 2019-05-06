# FtpConfigUpdate

**Install**

Bỏ `FtpConfig.php` => `/library/Custom/Controller/Batch/`

Bỏ `FtpConfigUpdate.php` + `FtpConfigRollback.php` => `/application/batch/controllers/`

**Usage:**

_>  php index.php development app FtpConfigUpdate `type` `param` `key` `old_value` `new_value`_

_> php index.php development app FtpConfigRollback `version` `param`_

**Update Batch:**

_>  Example: php index.php development app FtpConfigUpdate `update` `2019-05-03 00:00:00` `domain` `127.0.0.1` `127.0.0.1a`_

**Fix Update Batch (If connect FTP Fail):**

_>  Example: php index.php development app FtpConfigUpdate `fix` `Batch_FtpConfigUpdate_FtpFail_1557110064_1543762800` `domain` `127.0.0.1` `127.0.0.1a`_

**Rollback Batch:**

_> php index.php development app FtpConfigRollback `1.0` `Batch_FtpConfigUpdate_FtpSuccess_1557110064_1543762800`_

**Note::** 

_`version` được set trong file `FtpConfigUpdate.php`

_`type` chỉ được truyền `update` hoặc `fix`

_Ví dụ file log là: `/var/www/html/application/../log/Batch_FtpConfigUpdate_FtpLoginFail_1556855778.log`_

_> Thì `param` = `Batch_FtpConfigUpdate_FtpLoginFail_1556855778`_

