# Magento 2 Doofinder module

Generation of CSV file to import in Doofinder.

# Install

Download:

`composer require comunicart/magento2-doofinder`

Enable module:

`php bin/magento module:enable Comunicart_Doofinder`

# Usage

CSV generation will be done every 6 hours as cron task. In addition, you can also perform the generation manually through the console:

`php bin/magento doofinder:generate`

This command will create a CSV file in folder named *doofinder* inside *media* folder. The name of file will be the store view code. If you have more than one store view, generation process will create one file by store view.

Once CSV file (or files if you have more than one store view) is generated, you can provide CSV file URL in Doofinder settings page:

*https://www.domain.com/pub/media/doofinder/default.csv*
