# For commercial use, please contact the author for authorization. For non-commercial use, please indicate the source.
# License: CC BY-NC-SA 4.0
# Author: Axura
# URL: https://4xura.com/ctf/htb/htb-writeup-greenhorn/
# Source: Axura's Blog

from PIL import Image

image = Image.open('output-000.png')
print(image.mode)  # Should print 'RGB' for Depix to work
print(image.size)  # Should print the dimensions of the image