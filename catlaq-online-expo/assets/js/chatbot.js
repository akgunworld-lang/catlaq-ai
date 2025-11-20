import { fetch } from "@wordpress/api-fetch";

const SocialFeed = () => {
    fetch( { path: '/catlaq/v1/profiles' } )
        .then( response => console.log( 'Profiles', response ) )
        .catch( console.error );
};

SocialFeed();
