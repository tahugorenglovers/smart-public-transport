import pika
import json

def callback(ch, method, properties, body):
    data = json.loads(body)

    print("Received event:")
    print(data)

    speed = data["speed"]

    if speed == 0:
        print("Possible anomaly detected")
    else:
        print("Bus moving normally")


connection = pika.BlockingConnection(
    pika.ConnectionParameters(host='localhost')
)

channel = connection.channel()

exchange = "smarttransit"
queue = "ml.bus.location.queue"
routing_key = "bus.location.updated"

channel.exchange_declare(exchange=exchange, exchange_type='topic', durable=True)

channel.queue_declare(queue=queue, durable=True)
channel.queue_bind(exchange=exchange, queue=queue, routing_key=routing_key)

channel.basic_consume(
    queue=queue,
    on_message_callback=callback,
    auto_ack=True
)

print("Waiting for messages...")
channel.start_consuming()