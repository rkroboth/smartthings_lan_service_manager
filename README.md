# A generic php LAN Service Manager for use with SmartThings

This is a generic LAN Service manager app for use with SmartThings. It's intended to allow you to manage physical devices on a controller like a Raspberry Pi, yet control, monitor and automate those devices via the SmartThings app.

This was mainly written for my own use to run on a Raspberry Pi and control the Envisalink controller of my DSC security system inside my SmartThings app. However I attempted to make it very generic and include lots of documentation and comments, so anybody else could use it to create their own service.

PREREQUISITES:

- you need to know what a LAN Service Manager for SmartThings is and why you would need one
- you need to understand how to create SmartApps and DeviceHandlers in your SmartThings developer portal.
- you need a Linux box like a Raspberry Pi, with php 7 and php-curl installed, to run the php software on
- your SmartThings hub and the Linux box need to be on the same network and accessible to each other
- you need the ability to write php so you can write your own code to manage your devices, whatever they are


OVERVIEW:

The general idea is that you set up one or more "services" in the config of the php code. The service would be a controller that is not compatible with the SmartThings hub, but could be controlled over your LAN.  For example, a service might be a security system that is controlled via telnet commands. Your service php code is responsible for communicating with the controller, and managing the devices on it.

Inside each service, you set up one or more "devices".  In the example security system service above, the devices might be the door sensors, motions sensors, and the alarm.  

The example php config is configured to add an example service and some example devices to your SmartThings hub, which can be controlled in a very basic way by editing a json file. Thus you can start with the example files and create your own service on your Linux box.

SETUP:

- Create the LanServiceManagerSmartApp from the smarthings_hub directory in the SmartThings portal (don't add it to your app yet though)
- Create the 4 deviceHandlers from the smarthings_hub/devices directory in the SmartThings portal.
- Put the code from the service_manager directory onto your Linux box.
- In the service manager directory on the Linux box, copy the file config.example.php to config.php.
- Review the comments in the newly created config.php and edit the file as needed.
- Start the php software by running the file run.php
- In the SmartThings app under automation->smartapps, add the Lan Service Manager app
- Set the proper preferences while installing, including the IP of your Linux device. Other settings should match your config.php file


As I said, I wrote this to manage my Envisalink and DSC security system.  To do that, the files /service_manager/services/envisalink.php and /smartthings_hub/SilentAlertSmartApp.groovy are included.  However, they won't be needed by you unless you happen to also be trying to do this same thing.  However, the envisalink.php file might be another useful example of how to write your own service.

Feel free to email me with questions or feedback.

