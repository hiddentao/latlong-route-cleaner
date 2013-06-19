## What is it?

A script for filtering out erroneous points from a driving route consisting of lat-long-ts coordinates.

## How to use

    $ php clean.php points.csv

This will output the final route in CSV format to the console.

**Note: PHP 5.3 required. Tested with PHP 5.3.15 CLI**

## How it works

To figure whether a point on the route is likely a bad one it analyses 3 consecutive points at a time.

Let's say you have 3 consecutive ponts - A, B and C. If the speed when travelling from A to C through B exceeds the
known speed limit and the angle between AB and BC is quite tight then it's highly likely that point B is a bad
point that needs to be filtered out.

One could argue that it's possible for a driver to need to go a route which involves a tight angle turn. But in such
cases, even if the distance is quite large, I've reasoned that the speed of travel must still be reasonable. Furthermore,
if the angle at B is tight then the distance of AC must necessarily be very small compared to the distances AB and BC,
thus casting doubt on the idea that to get to C requires going through B.

I've chosen anything less than 10degrees as a tight angle, and 50 kmph as the speed limit. These are constants which
can be adjusted.

By plotting the original dataset into a line graph (longitude against latitude) we can ascertain that the following
data points are invalid. The algorithm currently successfully filters these out:

51.51138670225, -0.17560958862388, 1326379271
51.511520245835, -0.17449378967286, 1326379365
51.511306575914, -0.17294883728027, 1326379585
51.528290206973, -0.18110275268554, 1326380144
51.510371582676, -0.14917373657562, 1326380169
51.527188959817, -0.13130907659162, 1326380272
51.524659019479, -0.12767314910872, 1326380295


## Calculations

There is a commented-out method called `outputPointCalculations` which can be used to output detailed data on a set of
3 points, as such:

(51.525790599697, -0.13494283611591) ==> (51.527188959817, -0.13130907659162) ==> (51.526043275049, -0.13410749888364):
1 to 2: 0.19842533503601 km, 357.16560306482 km/h
2 to 3: 0.15885634896674 km, 114.37657125605 km/h
1 to 3: 0.039937568009853 km
Angle at 1: 6.9651900007574 deg
Angle at 2: 1.7470570983974 deg
Angle at 3: 171.28775290085 deg


## Further code improvements

Logic:
 * Could use actual mapping data to work out whether a route is possible or not. This would also mean knowing the
 different speed limits for different parts of the route.

Code:
 * When printing usage help we could dynamically figure out supported output formats in order to show to the user.
 * Refator ReaderFactory and WriteFactory such that they inherit from a base factory class which does most of the grunt work
 * Add testing


## License

Copyright (c) [Ramesh Nair](www.hiddentao.com) (“Author”)

All rights reserved.

The “Free as in Hugs” License

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.

Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.

Users of this software are permitted to offer to hug the Author or Contributors, free of charge or obligation.

THIS SOFTWARE AND ANY HUGS ARE PROVIDED BY THE AUTHOR AND CONTRIBUTORS “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL ANYONE BE HELD LIABLE FOR ACTUAL HUGS. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; LONELINESS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE. DON’T BE CREEPY.
