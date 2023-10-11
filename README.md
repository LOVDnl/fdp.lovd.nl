# LOVD FAIR Data Point (FDP)

This repository hosts the code running https://fdp.lovd.nl.
This work was part of the [NWO](https://www.nwo.nl/)-funded project FAIRifying
 the [LOVD3](https://github.com/LOVDnl/LOVD3) software.
As the first step of unlocking the LOVD3 instances, these must become Findable.
This is the purpose of the FDP.

This FDP supports both Turtle (`ttl`) and JSON-LD output.
The default is Turtle; provide an `Accept` HTTP header with a value
 `application/ld+json`, or add `?format=application/ld+json` to the URL to make
 the FDP switch to JSON-LD output.

## Installing this software

Note that if you'd like to have your LOVD included into the main LOVD FDP, you
 don't need this software.
Simply [contact us](https://LOVD.nl/contact) and let us know you'd like us to
 include your LOVD into our FDP, and we'll add it.
However, if you'd like to run your own FDP, displaying your own LOVD instance,
 you can use this code to get that FDP up and running with very little effort.
You'll require a webserver running PHP (at least 7.0), with JSON support
 enabled.

### Configuring your LOVD

To add your LOVD instance to this FDP, add your LOVD's ID to the list of LOVDs
 configured in the `class/api.fdp.php` file.
In the class' `__construct()` function, find the line reading:
```php
$aLOVDs = array(); // Here, your LOVD's unique ID can be filled in.
```
and add your LOVD's ID to this array, like:
```php
$aLOVDs = array('YOUR_LOVD_ID'); // Here, your LOVD's unique ID can be filled in.
```
You can add multiple LOVD IDs, if you like.

### Adding your FDP to the index

To add your FDP to the index at https://home.fairdatapoint.org/, 'ping' the
 index like so:

```bash
curl -sH "Content-Type: application/json" -d '{"clientUrl": "http://your.fdp.url"}' https://home.fairdatapoint.org
```

Don't forget to replace `your.fdp.url` with your FDP's URL.
Note that, for some reason, this command will not always work with a different
 client; there seems to be a check on user agent string.
Use Curl or pass a Curl user agent string when getting a `HTTP 403` response.

-----------

![NWO logo](https://www.nwo.nl/themes/custom/nwo/assets/images/logo.svg)

This publication is part of the project "Leiden FAIR variation datapoint:
 Developing a FAIR LOVD (LFVD)" (with project number 203.001.154 of the research
 programme Open Science Fund 2020/2021 which is (partly) financed by the Dutch
 Research Council (NWO).
