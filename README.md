# CommunityStore BTCpay
A BTCpayserver payment gateway for ConcreteCMS Community Store
Tested with: concreteCMS 9.13, PHP7.4, community store 2.4.8.5

You need concreteCMS running with CommunityStore installed and a functional BTCpayserver instance. Learn about BTCpayserver here: https://btcpayserver.org/
After filling in your credentials in the settings page of BTCpayserver plugin, it is advised to test the functionaliry with a low-value transaction. As of now, the plugin is built to handle BTC and Lightning transaction. 

This plugin relies on the greenfiel API (https://github.com/btcpayserver/btcpayserver-greenfield-php) to handle the communication with BTCpayserver. To obtain the required files, you must run composer install in the root directory of the plugin.
