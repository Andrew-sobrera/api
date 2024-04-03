import Image from '../models/image'

class UploaderController {

    async store(req, res) {

        const image = await Image.create({
            'url':req.file.key
        });
    
        res.json(image);
      }
}

export default new UploaderController()