import { spawnSync } from 'node:child_process';
// A short-lived test session avoids resetting the owner's password or retaining a secret in .env.
export function createLabSession(labRoot) {
    const args=['compose','--project-directory',labRoot,'-f',labRoot+'/compose.yaml','run','--rm','--no-deps','wpcli','eval'];
    const php=`if (!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('localhost','127.0.0.1'),true)) { WP_CLI::error('Local stand required.'); }
    $users=get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID'));if(!$users){WP_CLI::error('Administrator required.');}
    $id=(int)$users[0];$expiry=time()+1800;$token=WP_Session_Tokens::get_instance($id)->create($expiry);
    echo wp_json_encode(array('user'=>$id,'token'=>$token,'auth_name'=>AUTH_COOKIE,'logged_name'=>LOGGED_IN_COOKIE,'auth'=>wp_generate_auth_cookie($id,$expiry,'auth',$token),'logged'=>wp_generate_auth_cookie($id,$expiry,'logged_in',$token)));`;
    const result=spawnSync('docker',[...args,php],{encoding:'utf8',timeout:60000});
    if(result.status!==0)throw new Error('Could not create an isolated local browser session.');
    const session=JSON.parse(result.stdout.trim());
    return {session,close(){
        const cleanup=`WP_Session_Tokens::get_instance(${session.user})->destroy('${session.token}');`;
        const closed=spawnSync('docker',[...args,cleanup],{encoding:'utf8',timeout:60000});
        if(closed.status!==0)throw new Error('Could not revoke the temporary browser session.');
    }};
}
