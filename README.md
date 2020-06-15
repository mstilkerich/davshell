# davshell - Simple Shell to interact with (Card)DAV servers

This is a simple shell program to interact with a CardDAV server. It's main purpose is to serve as a simple demo client
for the [carddavclient library](https://github.com/mstilkerich/carddavclient) and a test utility during development of that library.

It currently provides little functionality interesting to an end user. The only thing noteworthy is that it supports cloning CardDAV addressbooks, to the same or a different server.

## Installation

To install davshell including its dependencies to a local directory, you can simply use composer:
```
composer.phar create-project -s dev --no-dev mstilkerich/davshell davshell
```

This installs the current master branch (`-s dev`) to the subdirectory `davshell`, without the development dependencies (`--no-dev`). To execute the shell, run `src/davshell` within the installation directory.

```
cd davshell
php src/davshell
```

You will get a warning like the following that the file `.davshellrc` was not found on the first run and all subsequent runs until an account has been added in the shell, which is then automatically stored inside this file.

```
PHP Warning:  file_get_contents(.davshellrc): failed to open stream: No such file or directory in davshell/src/Shell.php on line 406
```

## Usage

**A word of caution:** davshell will store the accounts you add and addressbooks you discover automatically in a file `.davshellrc` inside the current working directory, including the credentials. This means you do not have to enter the data every time you launch the shell again, but you should be aware that your password is stored inside that file. Furthermore, davshell stores a history of successful commands in the file `.davshell_history` within the working directory, which may also contain sensitive information such as passwords that were part of the commands. You should invoke davshell from within its installation directory so the configuration and history files are stored inside that directory.

Type `help` to get a list of the available commands, `help <command>` to get detailed help on a specific command.

### Example

1. Before interacting with a CardDAV server, we need to specify an account.
```
> add_account ExampleAccount example.com johndoe s3cretw0rd
```
This creates an account named `ExampleAccount` for the CardDAV service provided by `example.com`. The account name can be freely chosen and is only used to refer to the account inside davshell. Giving only the domain name assumes that the target domain is properly set up to allow discovery of the CardDAV service via the DNS SRV records or well-known URIs. If this is not the case, you can also provide a full URL to the location of the CardDAV service here.

2. Next, we discover the addressbooks on that account
```
> discover ExampleAccount
```
The output should indicate which addressbooks have been found.

3. Use the command `addressbooks` to get a list of all discovered addressbooks. The output might look something like this:
```
> addressbooks
ExampleAccount@0 - MyContacts (https://carddav.example.com:443/carddavhome/addressbook1/)
ExampleAccount@1 - MyContacts (https://carddav.example.com:443/carddavhome/addressbook2/)
```
The first column shows identifiers to refer to addressbooks inside davshell, composed of the account name and an index of the addressbook within the account, separated by @ (e.g., `ExampleAccount@0` refers to the first addressbook discovered for the account `ExampleAccount`.

4. You can repeat the above to add other accounts and discover their addressbooks. All entered accounts and discovered addressbooks are stored in `.davshellrc`, so upon starting the shell again the steps will not have to be repeated again.

5. Now you can interact with the addressbooks. You can
   - Show detailed properties of an addressbook using the `show_addressbook` command, including the reports supported by the server:
   ```
   > show_addressbook ExampleAccount@0
   ```
   - Run a synchronization process on the addressbook using the synchronize command
   ```
   > synchronize ExampleAccount@0
   ```
   Note this doesn't at this time actually synchronize anything, it emulates a synchronization against an local empty cache. But it tests that the synchronization mechanisms work against that server, and will print a list of the FN properties of all found cards.
   - Clone one addressbook to another (add parameter `-n` to only add non-existing address objects to the target)
   ```
   > clone ExampleAccount@0 AnotherAccount@0
   ```
   - Delete all address objects of an addressbook using  the `sweep` command
   ```
   > sweep AnotherAccount@0
   ```
