AWIN Feeder
===========
Wordpress Plugin
----------------

A [Wordpress](http://wordpress.org) plugin originally developed in 2011 for
helping with placement of affiliate products from [Affiliate Window](http://www.affiliatewindow.com) product feeds
into my numerous affiliate Wordpress blogs.

I'm cleaning up the code and releasing it open source for others to use
as they wish.

*As can be seen the system is far from finished but it did its job at the time.*

Forks, issues, bugs, etc are welcomed though I'll be doing my fair share
of tidying up too - see todo below.

**I strongly advise against installing this yourself yet. Although I have it working on a couple of Wordpress 
blogs it is highly unstable and unfinished.**

Features
--------

**1. Page & Post Tags - [aw-prodgrid] & [aw-prodblock]**

**[aw-prodgrid]**

This created a product grid wherever it was included in a page or post.

```[aw-prodgrid brand="Adidas" limit="6"]```

This would be replaced with a randomly selected list of 6
products with the brand name Adidas.

It had rudimentary searching ability so you could have a tag like this:

```[aw-prodgrid brand="Adidas" name="black" limit="6"]```

Example of the above tag output:

![Adidas Watches Black](http://i.imgur.com/fh2kySC.png)

The ```name``` attribute would be checked to ensure the title of the product contained that word.

**[aw-prodblock]**

Given a valid ID as an attribute this would output the product with its description and pricing.

**2. Widgets**

Two simple widgets were included:
 * Cheapest/Dearest Products - This widget would display a count of the top/bottom products by price.
 * Random by Brand - A very basic widget used to give random products of a particular brand.
