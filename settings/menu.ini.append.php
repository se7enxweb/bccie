<?php /* #?ini charset="utf-8"?

[NavigationPart]
Part[ezbccienavigationpart]=CIE

[TopAdminMenu]
Tabs[]=bccie_overview

[Topmenu_bccie_overview]
NavigationPartIdentifier=ezbccienavigationpart
Name=CIE
Tooltip=Collected Information Export Menu
URL[]
URL[default]=bccie/overview
Enabled[]
Enabled[default]=true
Enabled[browse]=false
Enabled[edit]=false
Shown[]
Shown[default]=true
Shown[edit]=true
Shown[navigation]=true
Shown[browse]=true
PolicyList[]=bccie/read

# The left menu of the module (parts/bccie/menu.tpl)
[Leftmenu_bccie]
Name=CIE
Links[]
Links[overview]=bccie/overview
Links[collected]=infocollector/overview
Links[project]=https://github.com/se7enxweb/bccie
LinkNames[]
LinkNames[overview]=Collected information export
LinkNames[collected]=Collected information
LinkNames[project]=Extension project
Enabled[]
Enabled[default]=true
Enabled[edit]=false
Enabled[browse]=false
PolicyList_overview[]=bccie/read
PolicyList_collected[]=infocollector/read

# Kept for installations whose settings name this block
[Leftmenu_bccie_overview]
Name=CIE
Links[]
LinkNames[]
Links[Export]=bccie/overview
Links[Extension project]=https://github.com/se7enxweb/bccie

*/ ?>
