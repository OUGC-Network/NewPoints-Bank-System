<h3 align="center">Newpoints Bank System</h3>

<div align="center">

[![Status](https://img.shields.io/badge/status-active-success.svg)]()
[![GitHub Issues](https://img.shields.io/github/issues/OUGC-Network/NewPoints-Bank.svg)](./issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/OUGC-Network/NewPoints-Bank.svg)](./pulls)
[![License](https://img.shields.io/badge/license-GPL-blue)](/LICENSE)

</div>

---

<p align="center"> Allow users to safeguard their points in a bank system.
    <br> 
</p>

## 📜 Table of Contents <a name = "table_of_contents"></a>

- [About](#about)
- [Getting Started](#getting_started)
    - [Dependencies](#dependencies)
    - [File Structure](#file_structure)
    - [Install](#install)
    - [Update](#update)
    - [Template Modifications](#template_modifications)
- [Settings](#settings)
- [Templates](#templates)
- [Usage](#usage)
- [Built Using](#built_using)
- [Authors](#authors)
- [Acknowledgments](#acknowledgement)
- [Support & Feedback](#support)

## 🚀 About <a name = "about"></a>

Allow users to safeguard their points in a bank system.

[Go up to Table of Contents](#table_of_contents)

## 📍 Getting Started <a name = "getting_started"></a>

The following information will assist you into getting a copy of this plugin up and running on your forum.

### Dependencies <a name = "dependencies"></a>

A setup that meets the following requirements is necessary to use this plugin.

- [MyBB](https://mybb.com/) >= 1.8
- PHP >= 7
- [Newpoints](https://github.com/OUGC-Network/Newpoints) >= 3.1

### File structure <a name = "file_structure"></a>

  ```
   .
   ├── inc
   │ ├── plugins
   │ │ ├── newpoints
   │ │ │ ├── languages
   │ │ │ │ ├── english
   │ │ │ │ │ ├── admin
   │ │ │ │ │ │ ├── newpoints_bank_system.lang.php
   │ │ │ │ │ ├── newpoints_bank_system.lang.php
   │ │ │ ├── plugins
   │ │ │ │ ├── BankSystem
   │ │ │ │ │ ├── hooks
   │ │ │ │ │ │ ├── admin.php
   │ │ │ │ │ │ ├── forum.php
   │ │ │ │ │ ├── settings
   │ │ │ │ │ │ ├── bank_system.json
   │ │ │ │ │ ├── templates
   │ │ │ │ │ │ ├── page_table_transactions.html
   │ │ │ │ │ │ ├── page_table_transactions_row.html
   │ │ │ │ │ ├── admin.php
   │ │ │ │ │ ├── core.php
   │ │ │ │ ├── newpoints_bank_system.php
   │ ├── tasks
   │ │ ├── newpoints_bank_system.php
   ```

### Installing <a name = "install"></a>

Follow the next steps in order to install a copy of this plugin on your forum.

1. Download the latest package.
2. Upload the contents of the _Upload_ folder to your MyBB root directory.
3. Browse to _Newpoints » Plugins_ and install this plugin by clicking _Install & Activate_.
4. Browse to _Newpoints » Settings_ to manage the plugin settings.

### Updating <a name = "update"></a>

Follow the next steps in order to update your copy of this plugin.

1. Browse to _Configuration » Plugins_ and deactivate this plugin by clicking _Deactivate_.
2. Follow step 1 and 2 from the [Install](#install) section.
3. Browse to _Configuration » Plugins_ and activate this plugin by clicking _Activate_.
4. Browse to _NewPoints_ to manage Newpoints modules.

### Template Modifications <a name = "template_modifications"></a>

No template edits are required for this plugin.

[Go up to Table of Contents](#table_of_contents)

## 🛠 Settings <a name = "settings"></a>

Below you can find a description of the plugin settings.

### Main Settings

- **Home Transactions** `numeric`
    - _Set how many last transactions to show in the Newpoints home page._

[Go up to Table of Contents](#table_of_contents)

## 📐 Templates <a name = "templates"></a>

The following is a list of templates available for this plugin.

- `newpoints_bank_system_page_table_transactions`
    - _front end_;
- `newpoints_bank_system_page_table_transactions_row`
    - _front end_;

[Go up to Table of Contents](#table_of_contents)

## 📖 Usage <a name="usage"></a>

The following is a description of additional configuration for this plugin.

[Go up to Table of Contents](#table_of_contents)

## ⛏ Built Using <a name = "built_using"></a>

- [MyBB](https://mybb.com/) - Web Framework
- [MyBB PluginLibrary](https://github.com/frostschutz/MyBB-PluginLibrary) - A collection of useful functions for MyBB
- [PHP](https://www.php.net/) - Server Environment

[Go up to Table of Contents](#table_of_contents)

## ✍️ Authors <a name = "authors"></a>

- [@Omar G](https://github.com/Sama34) - Idea & Initial work

See also the list of [contributors](https://github.com/OUGC-Network/NewPoints-Bank/contributors) who participated in
this project.

[Go up to Table of Contents](#table_of_contents)

## 🎉 Acknowledgements <a name = "acknowledgement"></a>

- [The Documentation Compendium](https://github.com/kylelobo/The-Documentation-Compendium)

[Go up to Table of Contents](#table_of_contents)

## 🎈 Support & Feedback <a name="support"></a>

This is free development and any contribution is welcome. Get support or leave feedback at the
official [MyBB Community](https://community.mybb.com/thread-159249.html).

Thanks for downloading and using our plugins!

[Go up to Table of Contents](#table_of_contents)