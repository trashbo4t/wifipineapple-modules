#!/bin/sh /etc/rc.common

# This is the auto-start script for Headers

START=200

start() {
    # Enable ip forward.
    echo 1 > /proc/sys/net/ipv4/ip_forward
    # Create symlink
    ln -s /pineapple/modules/Headers/includes/api /www/headers
    # Run iptables commands
    iptables -t nat -A PREROUTING -s 172.16.42.0/24 -p tcp --dport 80 -j DNAT --to-destination 172.16.42.1:80
    iptables -A INPUT -p tcp --dport 53 -j ACCEPT
}

stop() {
    rm /www/headers
    iptables -t nat -D PREROUTING -s 172.16.42.0/24 -p tcp --dport 80 -j DNAT --to-destination 172.16.42.1:80
    iptables -D INPUT -p tcp --dport 53 -j ACCEPT
    iptables -D INPUT -j DROP
}

disable() {
    rm /etc/rc.d/*headers
}
