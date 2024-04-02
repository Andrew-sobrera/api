import jwt from 'jsonwebtoken';

export default (req, res, next) => {
    const authorization = req.headers['authorization']

    if(!authorization){
        return res.status(401).json({
            errors: ['Login required'],
        })
    }
    const [, token] = authorization.split(' ')

    try{
        const dados = jwt.verify(token, process.env.TOKEN_SECRET)
        const { email } = dados
        req.userEmail = email
        return next();
    }catch(e){
        console.log(e)
    }
}
