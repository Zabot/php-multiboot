<?php

function getMenu($root) {
  $host = $_SERVER['HTTP_HOST'];
  $menu = ["#!ipxe"];

  array_push($menu, "menu Select Boot Option");

  $files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root),
    RecursiveIteratorIterator::SELF_FIRST);

  $ignored = ['.', '..'];
  $options = [];
  foreach($files as $file){
    $filename = $file->getFilename();
    $path = $file->getPathname();

    if (in_array($filename, $ignored)) continue;

    // Directories get a menu divider, not an entry
    if ($file->isDir()) {
      // TODO Only add directories with valid boot options
      array_push($menu, "item --gap -- ----- $filename -----");

    // Use the correct boot command depending on the file extension
    } else {
      $extension = pathinfo($path)['extension'];
      $httppath = "http://$host/$path";

      // ISOs get booted like SAN targets
      $boot_cmd = '';
      if ($extension == 'iso') {
        $boot_cmd = "sanboot $httppath";

      // EFI scripts get chainloaded
      } else if ($extension == 'pxe') {
        $boot_cmd = "chain --autofree $httppath";

      // TXT files contain custom entries
      } else if ($extension == 'txt') {
        $boot_cmd = trim(file_get_contents($path));
      }

      // If the extension was recognized, add it to the menu
      if ($boot_cmd) {
        $options[$filename] = $boot_cmd;
        array_push($menu, "item $filename Boot $filename");
      }
    }
  }

  // Display the menu selector
  array_push($menu, "choose boot");

  // Handle the user selection
  foreach($options as $label => $command) {
    array_push($menu, "iseq \${boot} $label && $command ||");
  }

  return $menu;
}

header("Content-Type: text/plain");

$menu = getMenu('./pxe');
foreach ($menu as $line)
  echo("$line\n");

