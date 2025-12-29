const {
  EmbedBuilder,
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
} = require('discord.js');

module.exports = async function postArrivalEmbed(client) {
  const channelId = '1454412970300936204';
  const channel = await client.channels.fetch(channelId);
  if (!channel) throw new Error('Arrival channel not found');

  const embed = new EmbedBuilder()
    .setTitle('🚀 Welcome to the HORIZON Interstellar Discord!')
    .setDescription(
      `You’ve reached the **Arrival Platform**, your journey begins here.

To continue beyond this point and unlock full server access, **account and membership verification is required**. Select the **Verify** button below and complete the process through our official members hub.

If verification does not complete successfully, or if you are joining us in a **Diplomatic capacity** or have any kind of question, please click **Need Help?** and create a support ticket. One of our officers will get to you as soon as possible.`
    )
    .setColor(0x2A78C8)
    .setFooter({ text: 'HORIZON Interstellar • Arrival Platform' });

  const buttons = new ActionRowBuilder().addComponents(
    new ButtonBuilder()
      .setLabel('Verify')
      .setStyle(ButtonStyle.Link)
      .setURL('https://horizoninterstellar.com/'),

    new ButtonBuilder()
      .setLabel('Need Help?')
      .setStyle(ButtonStyle.Link)
      .setURL(
        'https://discord.com/channels/113412259320954880/1454412972842811473'
      )
  );

  await channel.send({
    embeds: [embed],
    components: [buttons],
  });
};
