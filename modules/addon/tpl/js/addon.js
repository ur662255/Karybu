/**
 * @brief Add active / inactive toggle function for
 * fo_addon Form with the id as an argument to run after setting the value of the given addon
 **/
function doToggleAddon(addon, type) {
	if(typeof(type) == "undefined") type = "pc";
    var fo_obj = jQuery('#fo_addon').get(0);
    fo_obj.addon.value = addon;
    fo_obj.type.value = type;
    procFilter(fo_obj, toggle_activate_addon);
}
