API SETUP:
For the driver app's use, we need to produce an API set up on our website side that is going to let the app push a log of location data (coordinates). It will push it every minute using the API. On the website side, we need to just store that data in a log with the location and a time/date stamp and the driver the location is for.
BACK END ADMIN:
On admin side they need to be able to view a map at any time and it will show the latest location of any driver that is on a shift in that moment.
Also we need to be able to click a link that will be above the map that says "Location History". On that page there will be a few options.
First, select the driver. Then it automatically selects the most recent/current shift, but there's a drop down date selector to choose which date they want to see. Then they click "View Map".
Now it should show a map of the journey history of the logged location updates for that driver on that date. At the top some brief metrics/info (travelled miles, time, start and end time of the shift, total collections). It should also show a blip on the map of each collection from that date and that driver. So this map will show the journey, where the collections were and some details about the stats of the day/shift.
