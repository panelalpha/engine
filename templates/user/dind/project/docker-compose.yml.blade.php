services:
  dind:
    build:
      context: .
      dockerfile: ./Dockerfile
    image: ghcr.io/panelalpha/engine-user-dind:20260907
    pull_policy: missing
    restart: always
    hostname: {{ $user }}
    extra_hosts:
      - "host.docker.internal:host-gateway"
    {{ $isolation }}
    container_name: {{ $user }}
    # /run is ephemeral, the way it is on any Linux boot. Without this it is
    # part of the container's persistent overlay, so pidfiles outlive the
    # process that wrote them: a host reboot SIGKILLs the account before
    # dockerd can remove /run/docker.pid, and on the next start the init
    # script reads that pid, finds the number reused by something unrelated,
    # and refuses to start the daemon. The account comes up with no Docker
    # and every app in it stays down, silently. Sized because tmpfs pages
    # count against the account's own memory limit; real usage is ~276K.
    # Also holds supervisord.pid / supervisor.sock.
    tmpfs:
      - /run:mode=755,size=64m
    volumes:
      - /home/{{ $user }}/:/home/{{ $user }}/
      - ./entrypoint.sh:/entrypoint.sh
      - ./entrypoint.d/:/entrypoint.d/
      - ./supervisord.conf:/etc/supervisor/supervisord.conf:ro
      - ./supervisord.conf.d/:/etc/supervisor/conf.d/
    tty: true
    {{ !empty($cpu_limit) ? ("cpus: " . $cpu_limit) : "" }}
    {{ !empty($memory_limit) ? ("mem_limit: " . $memory_limit . "M") : "" }}
    {{ !empty($memory_limit) ? ("memswap_limit: " . $memory_limit . "M") : "" }}
@if ($device_read_bps || $device_write_bps)
    blkio_config:
@if ($device_read_bps)
      device_read_bps:
        - path: {{ $block_device }}
          rate: '{{ $device_read_bps }}'
@endif
@if ($device_write_bps)
      device_write_bps:
        - path: {{ $block_device }}
          rate: '{{ $device_write_bps }}'
@endif
@endif
networks:
  default:
    name: pash-default-network
    external: true
