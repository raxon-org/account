{{R3M}}
{{$register = Package.Raxon.Org.Account:Init:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Org.Account:Import:role.system()}}
{{$options = options()}}
/**
 // setup roles*
 // setup permissions*
 // setup jwt* (no patch, only force)
 // setup admin
 // setup user login (api.example.com)

 */
{{/if}}