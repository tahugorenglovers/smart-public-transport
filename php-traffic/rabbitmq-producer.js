const amqp = require("amqplib");

async function publishBusLocation() {
    try {
        const connection = await amqp.connect("amqp://guest:guest@localhost:5672");
        const channel = await connection.createChannel();

        const exchange = "smarttransit";
        const routingKey = "bus.location.updated";

        await channel.assertExchange(exchange, "topic", { durable: true });

        const payload = {
            bus_id: 1,
            latitude: -6.912,
            longitude: 107.610,
            speed: 34,
            timestamp: new Date().toISOString()
        };

        channel.publish(
            exchange,
            routingKey,
            Buffer.from(JSON.stringify(payload))
        );

        console.log("Published:", payload);

        setTimeout(() => {
            connection.close();
        }, 500);

    } catch (error) {
        console.error(error);
    }
}

publishBusLocation();