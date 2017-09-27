# PHP + LDAP + AD (Apache)

## Instrunction for install

For check is ldap install need run:

```ruby
echo function_exists('ldap_connect')?'Ldap connected':'Ldap error';
```

If after completing you will see `Ldap connected`, then all works as expected, else need install ldap.

You can read documentation <http://php.net/manual/ru/ldap.installation.php> OR make next steps:
- Edit  php.ini (uncomment `extension=php_ldap.dll`)
- Type in the PATH environment variables - `path_to_your_server\php\`. Preliminarily make sure, that on this path exist the files `libeay32.dll` and `ssleay32.dll`. OR (most extreme case) Copy the libraries `libeay32.dll` and `ssleay32.dll` into `C:\Windows\System32`
- You need be sure, that on the path of variable 'extension_dir' in the `php.ini` (default value is `extension_dir = path_to_your_server\php\ext\`) exist  the library `php_ldap.dll`
- Also you can check if the ldap library is connect, run in console the command `php -m`. In result you will see the modules:
```ruby
[PHP Modules]
a. 	...
b. 	ldap
c. 	...
```
- Restart Apache. If you will see `Ldap error`, please look which `php.ini` you are using at start your server, maybe you use php.ini which is in local Apach folder, and you have made settings in the different file.
