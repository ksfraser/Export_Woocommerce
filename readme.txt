/****************************************************************************
Name: EXPORT WOO
Free software under GNU GPL
*****************************************************************************/

WHAT DOES THIS MODULE DO?

This module takes your products and exports them to WooCommerce.

Steps:
	Init Tables (install/upgrade step)
	All Products Export
		Populate the Quantity On Hand (qoh) table
		Populate the Woo table
	Send Categories to WOO
	Product REST Export 	
		Module sends SIMPLE products first,
		Module sends Varialbe products second

	Orders Export/Import

	Customers Export/Import

	Maintain Variable Products

	Coupons

INSTALLATION:

1. FrontAccounting -> Setup -> Install/Activate Extensions

   Click on the icon in the right column corresponding to EXPORT_WOO

   Extensions drop down box -> Activated for (name of your business)

   Click on "active" box for EXPORT_WOO -> Update

2. FrontAccounting -> Setup -> Access Setup

   Select appropriate role click on EXPORT_WOO header and entry -> Save Role

   Logout and log back in

3. FrontAccounting -> Banking and General Ledger -> EXPORT_WOO

   Click on button -> Create Table
 
   Fill in details for connecting to the VTiger databases -> Update Mysql

----------------------------------------------------------

