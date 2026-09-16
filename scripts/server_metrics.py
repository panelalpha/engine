import mysql.connector
import psutil
import time
import json
import logging
import os
from datetime import datetime

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

def connect_to_db(config):
    try:
        connection = mysql.connector.connect(**config)
        if connection.is_connected():
            logging.info("Connected to database successfully.")
            return connection
    except mysql.connector.Error as e:
        logging.error(f"Database connection error: {e}")
    return None

def collect_metrics(prev_metrics):
    try:
        now = time.time()
        elapsed = now - prev_metrics.get("timestamp", now)
        if elapsed == 0:
            elapsed = 1  # avoid division by zero

        cpu = psutil.cpu_percent(interval=None)
        load_avg = psutil.getloadavg()

        disk_io = psutil.disk_io_counters()
        disk_read_Bps = (disk_io.read_bytes - prev_metrics.get("disk_read_bytes", 0)) / elapsed
        disk_write_Bps = (disk_io.write_bytes - prev_metrics.get("disk_write_bytes", 0)) / elapsed
        disk_read_iops = (disk_io.read_count - prev_metrics.get("disk_read_count", 0)) / elapsed
        disk_write_iops = (disk_io.write_count - prev_metrics.get("disk_write_count", 0)) / elapsed

        net_io = psutil.net_io_counters()
        net_in_Bps = (net_io.bytes_recv - prev_metrics.get("net_in_bytes", 0)) / elapsed
        net_out_Bps = (net_io.bytes_sent - prev_metrics.get("net_out_bytes", 0)) / elapsed
        net_in_pps = (net_io.packets_recv - prev_metrics.get("net_in_pkts", 0)) / elapsed
        net_out_pps = (net_io.packets_sent - prev_metrics.get("net_out_pkts", 0)) / elapsed

        prev_metrics.update({
            "timestamp": now,
            "disk_read_bytes": disk_io.read_bytes,
            "disk_write_bytes": disk_io.write_bytes,
            "disk_read_count": disk_io.read_count,
            "disk_write_count": disk_io.write_count,
            "net_in_bytes": net_io.bytes_recv,
            "net_out_bytes": net_io.bytes_sent,
            "net_in_pkts": net_io.packets_recv,
            "net_out_pkts": net_io.packets_sent,
        })

        return {
            "timestamp": datetime.fromtimestamp(now).strftime("%Y-%m-%d %H:%M:%S"),
            "cpu_percent": cpu,
            "cpu_load_avg_1": load_avg[0],
            "cpu_load_avg_5": load_avg[1],
            "cpu_load_avg_15": load_avg[2],
            "ram_percent": psutil.virtual_memory().percent,
            "swap_percent": psutil.swap_memory().percent,
            "disk_read_bps": disk_read_Bps,
            "disk_write_bps": disk_write_Bps,
            "disk_read_iops": disk_read_iops,
            "disk_write_iops": disk_write_iops,
            "net_in_bps": net_in_Bps,
            "net_out_bps": net_out_Bps,
            "net_in_pps": net_in_pps,
            "net_out_pps": net_out_pps,
        }

    except Exception as e:
        logging.error(f"Failed to collect system metrics: {e}")
        return None

def get_interval(default=3, min_value=3):
    interval = os.getenv("METRICS_SNAPSHOT_INTERVAL")
    if interval is not None:
        try:
            interval = int(interval)
            if interval < min_value:
                print(f"Warning: Interval is less than minimum value. Using default: {default}")
                return default
            return interval
        except ValueError:
            print("Warning: METRICS_SNAPSHOT_INTERVAL must be an integer. Using default value.")
    return default

def main():
    db_config = {
        "user": os.getenv("DB_USERNAME"),
        "password": os.getenv("DB_PASSWORD"),
        "host": "127.0.0.1",
        "database": os.getenv("DB_DATABASE"),
        "port": 3306,
    }

    if not all(db_config.values()):
        logging.error("One or more required environment variables are missing.")
        return

    connection = connect_to_db(db_config)
    if not connection:
        return

    cursor = connection.cursor()

    disk_io = psutil.disk_io_counters()
    net_io = psutil.net_io_counters()
    prev_metrics = {
        "timestamp": time.time(),
        "disk_read_bytes": disk_io.read_bytes,
        "disk_write_bytes": disk_io.write_bytes,
        "disk_read_count": disk_io.read_count,
        "disk_write_count": disk_io.write_count,
        "net_in_bytes": net_io.bytes_recv,
        "net_out_bytes": net_io.bytes_sent,
        "net_in_pkts": net_io.packets_recv,
        "net_out_pkts": net_io.packets_sent,
    }

    interval = get_interval()

    try:
        while True:
            time.sleep(interval)
            metrics = collect_metrics(prev_metrics)
            if not metrics:
                logging.error("Skipping insertion due to failed metrics collection.")
                time.sleep(1)
                continue
            query = """
                INSERT INTO server_metrics (
                    timestamp,
                    cpu_percent,
                    cpu_load_avg_1,
                    cpu_load_avg_5,
                    cpu_load_avg_15,
                    ram_percent,
                    swap_percent,
                    disk_read_bps,
                    disk_write_bps,
                    disk_read_iops,
                    disk_write_iops,
                    net_in_bps,
                    net_out_bps,
                    net_in_pps,
                    net_out_pps
                )
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """

            try:
                cursor.execute(query, (
                    metrics["timestamp"],
                    metrics["cpu_percent"],
                    metrics["cpu_load_avg_1"],
                    metrics["cpu_load_avg_5"],
                    metrics["cpu_load_avg_15"],
                    metrics["ram_percent"],
                    metrics["swap_percent"],
                    metrics["disk_read_bps"],
                    metrics["disk_write_bps"],
                    metrics["disk_read_iops"],
                    metrics["disk_write_iops"],
                    metrics["net_in_bps"],
                    metrics["net_out_bps"],
                    metrics["net_in_pps"],
                    metrics["net_out_pps"],
                ))
                connection.commit()
                # logging.info("Metrics inserted successfully.")
            except mysql.connector.Error as e:
                logging.error(f"Failed to insert metrics: {e}")

    except KeyboardInterrupt:
        logging.info("Terminating script.")
    except Exception as e:
        logging.error(f"Unexpected error: {e}")
    finally:
        if connection.is_connected():
            cursor.close()
            connection.close()
            logging.info("Database connection closed.")

if __name__ == "__main__":
    main()
