export function readCookie(name) {
    return document.cookie.split('; ').find(item => item.startsWith(name + '='))?.slice(name.length + 1);
}

export function setCookie(name, value, age = 86400) {
    document.cookie = `${name}=${value}; Path=/; Max-Age=${age}; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
}
