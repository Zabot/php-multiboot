# PXE Dynamic Multiboot
Dynamically generates an ipxe menu script from the contents of `pxe` in the same
directory as `meny.php`.

## Supported Files
* .iso: sanboot http://$host/path/to/iso
* .pxe: chain --autofree http://$host/path/to/pxe
* .txt: Command used is contents of txt file

