{{$register = Package.Raxon.Account:Init:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Account:Import:role.system()}}
{{Package.Raxon.Account:User:setup.anonymous(flags(), options())}}
{{Package.Raxon.Account:User:setup.user(flags(), options())}}
{{$options = options()}}
/**
 // setup roles*
 // setup permissions*
 // setup jwt* (no patch, only force)
 // setup admin
 // setup user login (api.example.com)

 */
{{/if}}