# PXE Dynamic Multiboot
Dynamically generates an ipxe menu script from the contents of a directory.

## Config
`config.ini` specifies the root directory for the menu

## Supported Files
* .iso: `sanboot http://$host/path/to/iso`
* .pxe: `chain --autofree http://$host/path/to/pxe`
* .txt: Command used is contents of txt file

# Building iPXE
The generated script should be booted from iPXE. You can embed a script into
an iPXE rom using the `EMBED=myscript.ipxe` flag during the inital build.

# Booting a kernel+ramdisk
Extract vmlinuz and initrd into a served directory. Create a file called `config.ini`.
A simple config file is shown:
```
[Alpine Vanilla]
kernel = vmlinuz-vanilla
initrd = initramfs-vanilla
bootargs = 'nomodeset modloop=${hostpath}/modloop-vanilla'
```
The section name will be displayed on the menu. Multiple sections can be used
to indicate multiple boot configurations. Directories containing a `config.ini`
will not be traversed for other bootable files.

The string `${hostpath}` can be used in bootargs to indicate the network
path of the directory containing config.ini.

